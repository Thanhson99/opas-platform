<?php

use App\Models\AutoCodingTask;
use App\Services\AutoCoding\AutoCodingAgentAuthService;
use App\Services\AutoCoding\AutoCodingTaskQueryService;
use App\Services\AutoCoding\LocalAutoCodingTaskService;
use App\Services\AutoCoding\LocalAutoCodingWorkerService;
use App\Services\AutoCoding\LocalMachineService;
use App\Services\AutoCoding\Telegram\AutoCodingTelegramBotConfigService;
use App\Services\AutoCoding\Telegram\AutoCodingTelegramBotService;
use App\Services\AutoCoding\Telegram\AutoCodingTelegramLegacyEnvImportService;
use App\Services\AutoCoding\Telegram\AutoCodingTelegramRuntimeConfigService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

// Keep the framework sample command separate from project-specific operational commands.
Artisan::command('inspire', function (OutputInterface $output) {
    $output->writeln(Inspiring::quote());
});

// Local execution commands let one machine create, claim, resume, and run auto-coding tasks.
Artisan::command(
    'opas:auto-coding:run {summary : Short local coding task summary} {--issue=} {--path=} {--validate} {--provider=} {--model=} {--dirty-policy=warn} {--scope=} {--scope-policy=warn}',
    function (LocalAutoCodingTaskService $taskService): void {
        $rawSummary = $this->argument('summary');
        $summary = is_string($rawSummary) ? $rawSummary : '';
        $issueKey = $this->option('issue');
        $repositoryPath = $this->option('path');
        $shouldRunValidation = (bool) $this->option('validate');
        $providerName = $this->option('provider');
        $modelName = $this->option('model');
        $dirtyPolicy = $this->option('dirty-policy');
        $scope = $this->option('scope');
        $scopePolicy = $this->option('scope-policy');

        $run = $taskService->runInspectionTask(
            $summary,
            is_string($issueKey) && $issueKey !== '' ? $issueKey : null,
            is_string($repositoryPath) && $repositoryPath !== '' ? $repositoryPath : null,
            $shouldRunValidation,
            is_string($providerName) && $providerName !== '' ? $providerName : null,
            [
                'model' => is_string($modelName) && $modelName !== '' ? $modelName : null,
            ],
            is_string($dirtyPolicy) && $dirtyPolicy !== '' ? $dirtyPolicy : 'warn',
            is_string($scope) && trim($scope) !== ''
                ? array_values(array_filter(array_map('trim', explode(',', $scope)), static fn (string $path): bool => $path !== ''))
                : [],
            is_string($scopePolicy) && $scopePolicy !== '' ? $scopePolicy : 'warn',
        );

        $report = $run->final_report;
        $this->info('Local auto-coding task completed.');
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
    }
)->purpose('Run one local-first auto-coding inspection task and print the structured report.');

Artisan::command(
    'opas:auto-coding:issue-token {--path=}',
    function (
        LocalMachineService $machineService,
        AutoCodingAgentAuthService $agentAuthService,
    ): int {
        $repositoryPath = $this->option('path');
        $machine = $machineService->resolve(
            is_string($repositoryPath) && $repositoryPath !== '' ? $repositoryPath : base_path('..'),
        );
        $token = $agentAuthService->issueToken($machine);

        $this->line(json_encode([
            'machine_id' => $machine->id,
            'machine_key' => $machine->machine_key,
            'repository_path' => $machine->repository_path,
            'access_token' => $token,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

        return Command::SUCCESS;
    }
)->purpose('Issue one machine access token for agent-facing auto-coding endpoints.');

Artisan::command(
    'opas:auto-coding:pull {--path=} {--execute}',
    function (LocalAutoCodingTaskService $taskService): int {
        $repositoryPath = $this->option('path');
        $shouldExecute = (bool) $this->option('execute');

        $task = $taskService->claimNextPendingTask(
            is_string($repositoryPath) && $repositoryPath !== '' ? $repositoryPath : null,
        );

        if (! $task instanceof AutoCodingTask) {
            $this->line(json_encode([
                'data' => null,
                'message' => 'No pending local auto-coding task available.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return Command::SUCCESS;
        }

        if ($shouldExecute) {
            $run = $taskService->executePendingTask($task->id);
            $this->line(json_encode($run->final_report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return Command::SUCCESS;
        }

        $this->line(json_encode([
            'id' => $task->id,
            'summary' => $task->summary,
            'issue_key' => $task->issue_key,
            'status' => $task->status->value,
            'repository_path' => $task->repository_path,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

        return Command::SUCCESS;
    }
)->purpose('Claim the next pending local auto-coding task and optionally execute it.');

Artisan::command(
    'opas:auto-coding:resume {taskId : The blocked local auto-coding task id} {response : Follow-up response text} {--token= : Resume token from the blocked task status payload}',
    function (LocalAutoCodingTaskService $taskService): int {
        $rawTaskId = $this->argument('taskId');
        $rawResponse = $this->argument('response');
        $rawToken = $this->option('token');

        if (
            ! is_numeric($rawTaskId)
            || ! is_string($rawResponse)
            || trim($rawResponse) === ''
            || ! is_string($rawToken)
            || trim($rawToken) === ''
        ) {
            $this->error('A numeric task id, non-empty response, and non-empty resume token are required.');

            return Command::FAILURE;
        }

        try {
            $run = $taskService->resumeBlockedTask((int) $rawTaskId, trim($rawResponse), trim($rawToken));
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                if (! is_array($messages)) {
                    continue;
                }

                foreach ($messages as $message) {
                    if (is_string($message) && $message !== '') {
                        $this->error($message);
                    }
                }
            }

            return Command::FAILURE;
        }

        $this->line(json_encode($run->final_report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

        return Command::SUCCESS;
    }
)->purpose('Resume one blocked local auto-coding task with follow-up input.');

Artisan::command(
    'opas:auto-coding:work {--path=} {--execute} {--interval=5} {--max-iterations=1}',
    function (LocalAutoCodingWorkerService $workerService): int {
        $repositoryPath = $this->option('path');
        $shouldExecute = (bool) $this->option('execute');
        $rawInterval = $this->option('interval');
        $intervalSeconds = is_numeric($rawInterval) ? max(0, (int) $rawInterval) : 5;
        $rawMaxIterations = $this->option('max-iterations');
        $maxIterations = is_numeric($rawMaxIterations) ? (int) $rawMaxIterations : 1;
        $iteration = 0;

        while ($maxIterations === 0 || $iteration < $maxIterations) {
            $iteration++;

            $payload = $workerService->runCycle(
                is_string($repositoryPath) && $repositoryPath !== '' ? $repositoryPath : null,
                $shouldExecute,
            );

            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            if ($maxIterations !== 0 && $iteration >= $maxIterations) {
                break;
            }

            if ($intervalSeconds > 0) {
                sleep($intervalSeconds);
            }
        }

        return Command::SUCCESS;
    }
)->purpose('Run the local auto-coding worker loop to heartbeat, claim, and optionally execute tasks.');

// Telegram operator commands manage webhook state and default bot command registration.
Artisan::command(
    'opas:auto-coding:telegram:default-bot:update {--locale= : Optional locale to store on the default Telegram bot}',
    function (AutoCodingTelegramBotConfigService $telegramBotConfigService): int {
        $config = $telegramBotConfigService->ensureDefaultBotExists();
        $rawLocale = $this->option('locale');
        $payload = [
            'key' => $config->key,
            'display_name' => $config->display_name,
        ];

        if (is_string($rawLocale) && trim($rawLocale) !== '') {
            $payload['locale'] = trim(strtolower($rawLocale)) === 'vi' ? 'vi' : 'en';
        }

        $updatedConfig = $telegramBotConfigService->update($config, $payload);

        $this->line(json_encode([
            'key' => $updatedConfig->key,
            'display_name' => $updatedConfig->display_name,
            'locale' => $updatedConfig->locale,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

        return Command::SUCCESS;
    }
)->purpose('Update lightweight default Telegram bot settings such as locale from operator scripts.');

Artisan::command(
    'opas:auto-coding:telegram:default-bot:import-env',
    function (AutoCodingTelegramLegacyEnvImportService $legacyEnvImportService): int {
        $config = $legacyEnvImportService->importDefaultBotFromEnv();

        if (! $config instanceof \App\Models\TelegramBotConfig) {
            $this->line(json_encode([
                'ok' => false,
                'message' => 'No legacy Telegram environment values were found to import.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return Command::FAILURE;
        }

        $this->line(json_encode([
            'ok' => true,
            'key' => $config->key,
            'display_name' => $config->display_name,
            'purpose' => $config->purpose,
            'environment' => $config->environment,
            'enabled' => $config->enabled,
            'locale' => $config->locale,
            'allowed_chat_ids_count' => count($config->allowed_chat_ids),
            'allowed_user_ids_count' => count($config->allowed_user_ids),
            'allowed_actions_count' => count($config->allowed_actions),
            'secret_status' => [
                'bot_token' => array_key_exists('bot_token', $config->secret_config),
                'webhook_secret' => array_key_exists('webhook_secret', $config->secret_config),
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

        return Command::SUCCESS;
    }
)->purpose('Bootstrap the default Telegram bot record from legacy AUTO_CODING_TELEGRAM_* environment values.');

Artisan::command(
    'opas:auto-coding:telegram:runtime',
    function (AutoCodingTelegramRuntimeConfigService $runtimeConfigService): int {
        $this->line(json_encode(
            $runtimeConfigService->getRuntimeConfig(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) ?: '{}');

        return Command::SUCCESS;
    }
)->purpose('Inspect the active Telegram bot runtime config resolved from the database.');

Artisan::command(
    'opas:auto-coding:telegram:webhook {url? : The webhook URL to register. Omit to inspect the current webhook info.} {--secret= : Optional secret token override for Telegram webhook registration} {--drop-pending-updates : Ask Telegram to discard queued updates during webhook registration}',
    function (AutoCodingTelegramBotService $telegramBotService): int {
        $rawUrl = $this->argument('url');
        $rawSecret = $this->option('secret');
        $dropPendingUpdates = (bool) $this->option('drop-pending-updates');

        if (! is_string($rawUrl) || trim($rawUrl) === '') {
            $payload = $telegramBotService->getWebhookInfo();
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return ($payload['ok'] ?? false) === true ? Command::SUCCESS : Command::FAILURE;
        }

        $payload = $telegramBotService->setWebhook(
            trim($rawUrl),
            is_string($rawSecret) && trim($rawSecret) !== '' ? trim($rawSecret) : null,
            $dropPendingUpdates,
        );

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

        return ($payload['ok'] ?? false) === true ? Command::SUCCESS : Command::FAILURE;
    }
)->purpose('Inspect or register the Telegram webhook used by the remote auto-coding bot.');

Artisan::command(
    'opas:auto-coding:telegram:webhook-delete {--drop-pending-updates : Ask Telegram to discard queued updates while deleting the webhook}',
    function (AutoCodingTelegramBotService $telegramBotService): int {
        $payload = $telegramBotService->deleteWebhook((bool) $this->option('drop-pending-updates'));

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

        return ($payload['ok'] ?? false) === true ? Command::SUCCESS : Command::FAILURE;
    }
)->purpose('Delete the Telegram webhook used by the remote auto-coding bot.');

Artisan::command(
    'opas:auto-coding:telegram:commands-sync',
    function (AutoCodingTelegramBotService $telegramBotService): int {
        $payload = $telegramBotService->setDefaultCommands();

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

        return ($payload['ok'] ?? false) === true ? Command::SUCCESS : Command::FAILURE;
    }
)->purpose('Sync the default Telegram bot commands for remote auto-coding control.');

// Inspection commands expose compact task summaries for local operators and scripts.
Artisan::command(
    'opas:auto-coding:list {--limit=10} {--status=} {--issue=}',
    function (AutoCodingTaskQueryService $taskQueryService): int {
        $rawLimit = $this->option('limit');
        $limit = is_numeric($rawLimit) ? max(1, min((int) $rawLimit, 50)) : 10;
        $status = $this->option('status');
        $issueKey = $this->option('issue');

        $tasks = $taskQueryService->getLatest(
            $limit,
            is_string($status) && $status !== '' ? $status : null,
            is_string($issueKey) && $issueKey !== '' ? $issueKey : null,
        );

        $payload = array_map(static function (AutoCodingTask $task): array {
            $latestRun = $task->runs->sortByDesc('id')->first();

            return [
                'id' => $task->id,
                'summary' => $task->summary,
                'issue_key' => $task->issue_key,
                'status' => $task->status->value,
                'branch_name' => $task->branch_name,
                'run_count' => $task->runs->count(),
                'latest_run_id' => $latestRun?->id,
                'artifact_count' => $latestRun?->artifacts->count(),
            ];
        }, $tasks);

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]');

        return Command::SUCCESS;
    }
)->purpose('List the latest local auto-coding tasks with compact run summaries.');

// Detailed inspection keeps one command focused on the latest run, artifacts, and report payload.
Artisan::command(
    'opas:auto-coding:show {taskId? : The local auto-coding task id} {--latest : Show the latest local auto-coding task}',
    function (): int {
        $taskId = $this->argument('taskId');
        $shouldShowLatest = (bool) $this->option('latest');

        $query = AutoCodingTask::query()
            ->with('runs.artifacts', 'runs.steps')
            ->orderByDesc('id');

        $task = is_numeric($taskId)
            ? $query->find((int) $taskId)
            : ($shouldShowLatest ? $query->first() : null);

        if (! $task instanceof AutoCodingTask) {
            $this->error('Local auto-coding task not found.');

            return Command::FAILURE;
        }

        /** @var \App\Models\AutoCodingTaskRun|null $latestRun */
        $latestRun = $task->runs->sortByDesc('id')->first();
        $artifactSummary = $latestRun !== null
            ? $latestRun->artifacts
                ->map(fn ($artifact): array => [
                    'id' => $artifact->getKey(),
                    'type' => $artifact->type,
                    'label' => $artifact->label,
                ])
                ->values()
                ->all()
            : [];

        $payload = [
            'task' => [
                'id' => $task->getKey(),
                'summary' => $task->summary,
                'issue_key' => $task->issue_key,
                'status' => $task->status->value,
                'repository_path' => $task->repository_path,
                'branch_name' => $task->branch_name,
                'completed_at' => $task->completed_at?->toIso8601String(),
            ],
            'runs' => [
                'count' => $task->runs->count(),
                'latest' => $latestRun !== null ? [
                    'id' => $latestRun->getKey(),
                    'status' => $latestRun->status->value,
                    'started_at' => $latestRun->started_at?->toIso8601String(),
                    'completed_at' => $latestRun->completed_at?->toIso8601String(),
                    'artifact_count' => $latestRun->artifacts->count(),
                    'step_count' => $latestRun->steps->count(),
                ] : null,
            ],
            'artifacts' => $artifactSummary,
            'latest_report' => $task->latest_report,
        ];

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

        return Command::SUCCESS;
    }
)->purpose('Show one local auto-coding task with its latest run and artifact summary.');
