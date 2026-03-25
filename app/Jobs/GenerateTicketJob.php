<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\TicketGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateTicketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public readonly int $orderId) {}

    public function handle(TicketGenerationService $generator): void
    {
        $order = Order::with('items')->find($this->orderId);

        if (! $order) {
            Log::warning("GenerateTicketJob: Order {$this->orderId} not found.");
            return;
        }

        if (! $order->isPaid()) {
            Log::warning("GenerateTicketJob: Order {$order->reference} is not paid ({$order->status}). Aborting.");
            return;
        }

        // Idempotent: skip if tickets already generated
        if ($order->issuedTickets()->exists()) {
            Log::info("GenerateTicketJob: Tickets already generated for {$order->reference}.");
            SendTicketEmailJob::dispatch($this->orderId);
            return;
        }

        $generator->generate($order);

        Log::info("GenerateTicketJob: Tickets generated for Order {$order->reference}.");

        SendTicketEmailJob::dispatch($this->orderId);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("GenerateTicketJob failed for Order {$this->orderId}: " . $e->getMessage());
    }
}
