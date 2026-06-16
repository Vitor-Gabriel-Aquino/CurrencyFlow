<?php

namespace App\Console\Commands;

use App\Application\PaymentRequests\ExpirePendingPaymentRequests;
use Illuminate\Console\Command;

class ExpirePendingPaymentRequestsCommand extends Command
{
    protected $signature = 'payment-requests:expire-pending
        {--batch=100 : Maximum pending payment requests to process per batch}';

    protected $description = 'Expire pending payment requests whose review window has passed.';

    public function handle(ExpirePendingPaymentRequests $expirePendingPaymentRequests): int
    {
        $batchSize = (int) $this->option('batch');

        if ($batchSize < 1) {
            $this->error('The batch option must be at least 1.');

            return self::FAILURE;
        }

        $expiredCount = $expirePendingPaymentRequests->handle($batchSize);

        $this->info("Expired {$expiredCount} pending payment request(s).");

        return self::SUCCESS;
    }
}
