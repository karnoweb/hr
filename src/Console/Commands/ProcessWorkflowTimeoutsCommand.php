<?php

namespace Karnoweb\Hr\Console\Commands;

use Illuminate\Console\Command;
use Karnoweb\Hr\Enums\ApprovalStatus;
use Karnoweb\Hr\Models\DocumentApproval;
use Karnoweb\Hr\Services\DocumentService;

/**
 * Process overdue workflow approvals (HR-131).
 */
class ProcessWorkflowTimeoutsCommand extends Command
{
    protected $signature = 'hr:process-workflow-timeouts';

    protected $description = 'Apply timeout_action to overdue pending document approvals';

    public function handle(DocumentService $documents): int
    {
        $due = DocumentApproval::query()
            ->where('status', ApprovalStatus::Pending)
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<=', now())
            ->get();

        $processed = 0;

        foreach ($due as $approval) {
            $documents->applyTimeoutAction($approval);
            $processed++;
        }

        $this->info("Processed {$processed} overdue approval(s).");

        return self::SUCCESS;
    }
}
