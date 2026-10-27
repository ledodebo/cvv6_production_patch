<?php

namespace App\Jobs;

use App\Services\DigitalOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class FulfillDigitalOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $orderId;
    public int $tries = 5;
    public int $backoff = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Execute the job non-blockingly.
     */
    public function handle(DigitalOrderService $service): void
    {
        $attempt = $this->attempts();
        Log::info("FulfillDigitalOrderJob starting attempt {$attempt}/5 for order #{$this->orderId}");

        $result = $service->fulfillOrder($this->orderId);

        // If supplier returned pending/processing, release back to queue for 5 seconds without sleeping
        if (!$result['success'] && ($result['status'] ?? '') === 'pending') {
            if ($attempt < 5) {
                Log::info("Order #{$this->orderId} status is pending, releasing back to queue for 5 seconds (attempt {$attempt}/5)");
                $this->release(5);
                return;
            } else {
                Log::warning("Order #{$this->orderId} exhausted all 5 polling attempts. Marking supplier_pending_review.");
                $service->markSupplierPendingReview($this->orderId);
                return;
            }
        }

        Log::info("FulfillDigitalOrderJob finished for order #{$this->orderId}: " . json_encode($result));
    }

    /**
     * Handle job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error("FulfillDigitalOrderJob permanently failed for order #{$this->orderId}: " . ($exception ? $exception->getMessage() : 'Unknown error'));
        try {
            app(DigitalOrderService::class)->markSupplierPendingReview($this->orderId);
        } catch (\Throwable $e) {
            //
        }
    }
}
