<?php

namespace App\Console\Commands;

use App\Services\Supplier\FoxReload\FoxReloadClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckSupplierBalance extends Command
{
    protected $signature = 'supplier:check-balance';
    protected $description = 'Check FoxReload supplier balance and alert if below threshold';

    public function handle(FoxReloadClient $client): int
    {
        $this->info("Checking FoxReload supplier balances...");
        $res = $client->getBalances();

        if (empty($res['success'])) {
            $err = $res['error'] ?? 'Unknown error';
            $this->error("Failed to fetch FoxReload balance: {$err}");
            Log::warning("CheckSupplierBalance failed: {$err}");
            return 1;
        }

        $usdBalance = $client->getUsdBalance();
        $this->info("Current USD Balance: $" . number_format($usdBalance ?? 0, 2));

        $threshold = (float) config('digital_purchase.foxreload.balance_alert_threshold_usd', 50.0);

        if ($usdBalance !== null && $usdBalance < $threshold) {
            $msg = "ALERT: FoxReload USD balance is low ($" . number_format($usdBalance, 2) . " < $" . number_format($threshold, 2) . ")";
            $this->warn($msg);
            Log::alert($msg);
        } else {
            $this->info("Balance is healthy (above threshold of $" . number_format($threshold, 2) . ")");
        }

        return 0;
    }
}
