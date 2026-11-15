<?php

namespace App\Console\Commands;

use App\Actions\Billing\SyncSubscriptionStatuses;
use Illuminate\Console\Command;

class SyncBilling extends Command
{
    protected $signature = 'billing:sync {--dry-run : Report what would change without applying it}';

    protected $description = 'Renew, convert, expire subscriptions and tidy pending orders and licenses';

    public function handle(SyncSubscriptionStatuses $sync): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $result = $sync->handle($dryRun);

        $this->info($dryRun ? 'Billing sync (dry run)' : 'Billing sync complete');

        $this->table(
            ['Action', 'Count'],
            [
                ['Trials converted', $result['trials_converted']],
                ['Subscriptions renewed', $result['subscriptions_renewed']],
                ['Subscriptions expired', $result['subscriptions_expired']],
                ['Orders expired', $result['orders_expired']],
                ['Licenses expired', $result['licenses_expired']],
            ],
        );

        return self::SUCCESS;
    }
}
