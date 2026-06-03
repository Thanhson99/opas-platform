<?php

declare(strict_types=1);

namespace App\Services\AutoCoding\Telegram;

use App\Models\AutoCodingMachine;
use App\Models\AutoCodingTask;

class AutoCodingTelegramHelpMenuFormatter
{
    public function __construct(
        private readonly AutoCodingTelegramFormatterSupport $support,
    ) {}

    /**
     * Build the onboarding and help text for Telegram remote coding control.
     *
     * @param  array<int, AutoCodingTask>  $tasks
     * @param  AutoCodingMachine|null  $machine
     * @param  array<string, mixed>|null  $chatSession
     * @return string
     */
    public function formatHelp(array $tasks = [], ?AutoCodingMachine $machine = null, ?array $chatSession = null): string
    {
        $lines = [
            $this->support->text('help.title'),
            '',
            $this->support->label('home_dashboard'),
        ];

        $machineLines = $this->support->buildMachineDashboardLines($machine);

        if ($machineLines === []) {
            $lines[] = $this->support->text('help.no_worker_reported');
        } else {
            array_push($lines, ...$machineLines);
        }

        $chatSessionLines = $this->support->buildChatSessionDashboardLines($chatSession);

        if ($chatSessionLines !== []) {
            $lines[] = '';
            array_push($lines, ...$chatSessionLines);
        }

        $lines = array_merge(
            $lines,
            [''],
            [$this->support->label('quick_start')],
            $this->support->textLines('help.quick_start_steps'),
            [''],
            [$this->support->label('commands')],
            $this->support->textLines('help.commands'),
            [''],
            [$this->support->text('help.buttons_hint')],
            [''],
            [$this->support->label('activity_snapshot')],
        );

        $activityLines = $this->support->buildTaskActivityLines($tasks);

        if ($activityLines === []) {
            $lines[] = $this->support->text('help.no_active_tasks');
        } else {
            array_push($lines, ...$activityLines);
        }

        $attentionLines = $this->support->buildAttentionTaskLines($tasks);

        if ($attentionLines !== []) {
            $lines[] = '';
            $lines[] = $this->support->label('needs_attention');
            array_push($lines, ...$attentionLines);
        }

        $lines[] = '';
        $lines[] = $this->support->label('current_tasks');

        if ($tasks === []) {
            $lines[] = $this->support->text('help.no_active_tasks');
        } else {
            foreach ($tasks as $task) {
                $lines[] = $this->support->formatQueueTaskLine($task);
            }
        }

        $lines[] = '';
        $lines[] = $this->support->text('help.tip');

        return implode("\n", $lines);
    }

    /**
     * Build one localized menu description for a named Telegram navigation menu.
     *
     * @param  string  $menuKey
     * @param  array<int, AutoCodingTask>  $tasks
     * @return string
     */
    public function formatMenu(string $menuKey, array $tasks = []): string
    {
        return match (trim(strtolower($menuKey))) {
            'queue' => implode("\n", [
                $this->support->label('queue_management'),
                '',
                ...$this->support->textLines('menus.queue'),
            ]),
            default => $this->formatHelp($tasks),
        };
    }
}
