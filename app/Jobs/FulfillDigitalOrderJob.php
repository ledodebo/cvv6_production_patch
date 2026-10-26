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
    public int $tries = 3;
    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Execute the job.
     */
    public function handle(DigitalOrderService $service): void
    {
        Log::info("FulfillDigitalOrderJob starting for order #{$this->orderId}");
        $result = $service->fulfillOrder($this->orderId);
        Log::info("FulfillDigitalOrderJob finished for order #{$this->orderId}: " . json_encode($result));
    }

    /**
     * Handle job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error("FulfillDigitalOrderJob permanently failed for order #{$this->orderId}: " . ($exception ? $exception->getMessage() : 'Unknown error'));
    }
}
