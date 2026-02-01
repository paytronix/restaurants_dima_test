<?php

namespace App\Console\Commands;

use App\Models\PaymentTransaction;
use App\Services\Payment\PaymentService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile
                            {--provider= : Payment provider to reconcile (required)}
                            {--since= : Only reconcile transactions created after this date (Y-m-d format)}
                            {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Reconcile payment transactions with provider status';

    public function __construct(
        private PaymentService $paymentService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $provider = $this->option('provider');

        if (empty($provider)) {
            $this->error('The --provider option is required');

            return self::FAILURE;
        }

        $since = $this->option('since');
        $dryRun = $this->option('dry-run');

        $sinceDate = null;
        if (! empty($since)) {
            try {
                $sinceDate = Carbon::parse($since);
            } catch (\Exception $e) {
                $this->error("Invalid date format: {$since}. Use Y-m-d format.");

                return self::FAILURE;
            }
        }

        $this->info("Reconciling {$provider} payments".($dryRun ? ' (dry run)' : ''));

        if ($sinceDate !== null) {
            $this->info("Only transactions created after: {$sinceDate->toDateString()}");
        }

        $query = PaymentTransaction::nonTerminal()
            ->forProvider($provider)
            ->whereNotNull('provider_payment_id');

        if ($sinceDate !== null) {
            $query->where('created_at', '>=', $sinceDate);
        }

        $transactions = $query->get();

        if ($transactions->isEmpty()) {
            $this->info('No transactions to reconcile');

            return self::SUCCESS;
        }

        $this->info("Found {$transactions->count()} transactions to reconcile");

        $stats = [
            'unchanged' => 0,
            'updated' => 0,
            'mismatch' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $this->withProgressBar($transactions, function ($transaction) use ($dryRun, &$stats) {
            if ($dryRun) {
                $stats['skipped']++;

                return;
            }

            $result = $this->paymentService->reconcileTransaction($transaction);

            match ($result->status) {
                'unchanged' => $stats['unchanged']++,
                'updated' => $stats['updated']++,
                'mismatch' => $stats['mismatch']++,
                'skipped' => $stats['skipped']++,
                'failure' => $stats['failed']++,
                default => null,
            };

            if ($result->wasUpdated()) {
                $this->newLine();
                $this->info("  Transaction #{$transaction->id}: {$result->oldStatus} -> {$result->newStatus}");
            }

            if ($result->hasMismatch()) {
                $this->newLine();
                $this->warn("  Transaction #{$transaction->id}: Mismatch - local: {$result->oldStatus}, provider: {$result->newStatus}");
            }
        });

        $this->newLine(2);
        $this->info('Reconciliation complete:');
        $this->table(
            ['Status', 'Count'],
            [
                ['Unchanged', $stats['unchanged']],
                ['Updated', $stats['updated']],
                ['Mismatch (cannot transition)', $stats['mismatch']],
                ['Skipped', $stats['skipped']],
                ['Failed', $stats['failed']],
            ]
        );

        return self::SUCCESS;
    }
}
