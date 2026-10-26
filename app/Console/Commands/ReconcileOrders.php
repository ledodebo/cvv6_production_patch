<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Supplier\FoxReload\FoxReloadClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileOrders extends Command
{
    protected $signature = 'orders:reconcile {--hours=24 : Number of hours to inspect}';
    protected $description = 'Reconcile local digital orders against FoxReload supplier records';

    public function handle(FoxReloadClient $client): int
    {
        $hours = (int) $this->option('hours');
        $this->info("Reconciling digital orders from the past {$hours} hours...");

        $orders = Order::whereNotNull('foxreload_order_id')
            ->where('created_at', '>=', now()->subHours($hours))
            ->get();

        $this->info("Found " . $orders->count() . " supplier orders to reconcile.");

        $mismatches = 0;
        foreach ($orders as $order) {
            $statusRes = $client->getOrderStatus($order->foxreload_order_id);
            if (!empty($statusRes['success']) && !empty($statusRes['data']['status'])) {
                $remoteStatus = $statusRes['data']['status'];
                $localStatus = $order->fulfillment_status;

                if ($remoteStatus === 'completed' && $localStatus !== 'delivered') {
                    $this->warn("Mismatch for Order #{$order->id}: FoxReload is 'completed' but local is '{$localStatus}'");
                    Log::warning("Order #{$order->id} reconciliation mismatch: supplier completed, local {$localStatus}");
                    $mismatches++;
                } elseif (in_array($remoteStatus, ['failed', 'cancelled']) && $localStatus === 'delivered') {
                    $this->error("CRITICAL for Order #{$order->id}: FoxReload is '{$remoteStatus}' but local is 'delivered'!");
                    Log::critical("Order #{$order->id} reconciliation CRITICAL: supplier {$remoteStatus}, local delivered");
                    $mismatches++;
                }
            }
        }

        if ($mismatches === 0) {
            $this->info("Reconciliation complete. All orders match 100%.");
        } else {
            $this->warn("Reconciliation found {$mismatches} mismatches. Check logs for details.");
        }

        return 0;
    }
}
