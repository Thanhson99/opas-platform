<?php

use App\Models\AutoCodingTask;
use App\Services\AutoCoding\AutoCodingAgentAuthService;
use App\Services\AutoCoding\AutoCodingTaskQueryService;
use App\Services\AutoCoding\LocalAutoCodingTaskService;
use App\Services\AutoCoding\LocalAutoCodingWorkerService;
use App\Services\AutoCoding\LocalMachineService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

Artisan::command('inspire', function (OutputInterface $output) {
    $output->writeln(Inspiring::quote());
});

Artisan::command(
    'opas:auto-coding:run {summary : Short local coding task summary} {--issue=} {--path=} {--validate} {--provider=} {--model=}',
    function (LocalAutoCodingTaskService $taskService): void {
        $rawSummary = $this->argument('summary');
        $summary = is_string($rawSummary) ? $rawSummary : '';
        $issueKey = $this->option('issue');
        $repositoryPath = $this->option('path');
        $shouldRunValidation = (bool) $this->option('validate');
        $providerName = $this->option('provider');
        $modelName = $this->option('model');

        $run = $taskService->runInspectionTask(
            $summary,
            is_string($issueKey) && $issueKey !== '' ? $issueKey : null,
            is_string($repositoryPath) && $repositoryPath !== '' ? $repositoryPath : null,
            $shouldRunValidation,
            is_string($providerName) && $providerName !== '' ? $providerName : null,
            [
                'model' => is_string($modelName) && $modelName !== '' ? $modelName : null,
            ],
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

Artisan::command(
    'opas:auto-coding:show {taskId? : The local auto-coding task id} {--latest : Show the latest local auto-coding task}',
    function (): int {
        $taskId = $this->argument('taskId');
        $shouldShowLatest = (bool) $this->option('latest');

        $query = AutoCodingTask::query()
            ->with('runs.artifacts')
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
                ] : null,
            ],
            'artifacts' => $artifactSummary,
            'latest_report' => $task->latest_report,
        ];

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

        return Command::SUCCESS;
    }
)->purpose('Show one local auto-coding task with its latest run and artifact summary.');
