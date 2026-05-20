<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\AutoCoding\LocalAutoCodingTaskService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteAutoCodingTaskJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create one queued local auto-coding execution job.
     *
     * @param  int  $taskId
     * @return void
     */
    public function __construct(
        public readonly int $taskId,
    ) {}

    /**
     * Execute the queued local auto-coding task.
     *
     * @param  LocalAutoCodingTaskService  $taskService
     * @return void
     */
    public function handle(LocalAutoCodingTaskService $taskService): void
    {
        $taskService->executePendingTask($this->taskId);
    }
}
