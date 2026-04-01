<?php

namespace App\Jobs;

use App\Mail\TicketIssuedMail;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTicketEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 120;

    public function __construct(public readonly int $orderId) {}

    public function handle(): void
    {
        $order = Order::with(['items.ticket.event', 'issuedTickets.ticket.event'])->find($this->orderId);

        if (! $order) {
            Log::warning("SendTicketEmailJob: Order {$this->orderId} not found.");
            return;
        }

        Mail::to($order->email)->send(new TicketIssuedMail($order));

        Log::info("SendTicketEmailJob: Email sent for Order {$order->reference} to {$order->email}.");
    }

    public function failed(\Throwable $e): void
    {
        Log::error("SendTicketEmailJob failed for Order {$this->orderId}: " . $e->getMessage());
    }
}
