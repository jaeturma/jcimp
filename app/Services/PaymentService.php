<?php

namespace App\Services;

use App\Jobs\GenerateTicketJob;
use App\Models\ManualPayment;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Ticket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymentService
{
    /**
     * Create an order from one or more reservations (cart checkout).
     *
     * Each reservation becomes one OrderItem. All reservations are consumed
     * (deleted) — their reserved_quantity will be converted to sold_quantity
     * when the order is paid via markPaid().
     *
     * @param  Collection<int, Reservation>  $reservations  Pre-loaded with 'ticket' relation
     */
    public function createOrder(Collection $reservations, string $email, string $paymentMethod): Order
    {
        return DB::transaction(function () use ($reservations, $email, $paymentMethod) {
            $total = $reservations->sum(fn (Reservation $r) => $r->ticket->price * $r->quantity);

            $order = Order::create([
                'reference'      => Order::generateReference(),
                'email'          => $email,
                'status'         => 'pending',
                'payment_method' => $paymentMethod,
                'total_amount'   => $total,
            ]);

            foreach ($reservations as $reservation) {
                $order->items()->create([
                    'ticket_id' => $reservation->ticket_id,
                    'quantity'  => $reservation->quantity,
                    'price'     => $reservation->ticket->price,
                ]);
            }

            return $order;
        });
    }

    /**
     * Initiate a QR Ph (PayMongo/Xendit) payment.
     * Returns the gateway's checkout URL.
     */
    public function initiateQrPh(Order $order): string
    {
        // TODO: integrate with PayMongo / Xendit
        // For now return a placeholder URL structure
        $gatewayRef = 'GW-' . strtoupper(uniqid());
        $order->update(['gateway_reference' => $gatewayRef]);

        // Return the QR payment URL from the gateway
        // e.g. return PayMongoService::createSource($order);
        return route('payment.qr-pending', ['reference' => $order->reference]);
    }

    /**
     * Store manual payment proof and mark the order as pending_verification.
     */
    public function submitManualProof(Order $order, UploadedFile $file): ManualPayment
    {
        $path = $file->store('payment_proofs', 'private');

        return DB::transaction(function () use ($order, $path) {
            $order->update(['status' => 'pending_verification']);

            return ManualPayment::create([
                'order_id'    => $order->id,
                'proof_image' => $path,
                'status'      => 'pending',
            ]);
        });
    }

    /**
     * Mark an order as paid (called from webhook or admin approval).
     * Idempotent — safe to call multiple times.
     */
    public function markPaid(Order $order): void
    {
        if ($order->isPaid()) {
            Log::info("Order {$order->reference} already paid — skipping.");
            return;
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'paid']);

            $ticketIds = $order->items->pluck('ticket_id');

            // Convert reserved → sold for each ticket in the order
            foreach ($order->items as $item) {
                Ticket::where('id', $item->ticket_id)->update([
                    'reserved_quantity' => DB::raw("GREATEST(0, reserved_quantity - {$item->quantity})"),
                    'sold_quantity'     => DB::raw("sold_quantity + {$item->quantity}"),
                ]);
            }

            // Delete any remaining reservations for this email + tickets.
            // Reservations served their purpose (holding inventory during checkout)
            // and are no longer needed once the order is confirmed paid.
            Reservation::where('email', $order->email)
                ->whereIn('ticket_id', $ticketIds)
                ->delete();
        });

        GenerateTicketJob::dispatch($order->id);
    }

    /**
     * Directly issue tickets for a walk-in / cash / e-wallet payment.
     *
     * Creates an order already in 'paid' status — no reservation or proof needed.
     * Dispatches ticket generation + email immediately after.
     *
     * @param  string       $email         Recipient email
     * @param  array        $items         [['ticket_id' => int, 'quantity' => int], ...]
     * @param  string       $paymentMethod 'cash' | 'gcash' | 'paymaya' | 'gotyme' | 'manual' | 'qrph'
     * @param  string|null  $referenceNo   Optional payment reference / transaction number
     * @throws RuntimeException            If any ticket has insufficient availability
     */
    public function directIssue(string $email, array $items, string $paymentMethod = 'cash', ?string $referenceNo = null): Order
    {
        $order = DB::transaction(function () use ($email, $items, $paymentMethod, $referenceNo) {
            $itemData = [];

            foreach ($items as $item) {
                $ticket   = Ticket::lockForUpdate()->findOrFail($item['ticket_id']);
                $quantity = (int) $item['quantity'];

                if ($ticket->availableQuantity() < $quantity) {
                    throw new RuntimeException("Not enough tickets available for: {$ticket->name}");
                }

                $itemData[] = ['ticket' => $ticket, 'quantity' => $quantity, 'price' => $ticket->price];
            }

            $total = array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $itemData));

            $order = Order::create([
                'reference'         => Order::generateReference(),
                'email'             => $email,
                'status'            => 'paid',
                'payment_method'    => $paymentMethod,
                'total_amount'      => $total,
                'gateway_reference' => $referenceNo ?: null,
            ]);

            foreach ($itemData as $item) {
                $order->items()->create([
                    'ticket_id' => $item['ticket']->id,
                    'quantity'  => $item['quantity'],
                    'price'     => $item['price'],
                ]);

                Ticket::where('id', $item['ticket']->id)->update([
                    'sold_quantity' => DB::raw("sold_quantity + {$item['quantity']}"),
                ]);
            }

            return $order;
        });

        GenerateTicketJob::dispatch($order->id);

        return $order;
    }

    /**
     * Mark an order as failed / rejected.
     */
    public function markFailed(Order $order, string $reason = ''): void
    {
        $order->update(['status' => 'failed']);

        Log::warning("Order {$order->reference} failed: {$reason}");
    }

    /**
     * Approve a manual payment (admin action).
     */
    public function approveManualPayment(ManualPayment $payment, int $adminUserId): void
    {
        DB::transaction(function () use ($payment, $adminUserId) {
            $payment->update([
                'status'      => 'approved',
                'reviewed_by' => $adminUserId,
                'reviewed_at' => now(),
            ]);
        });

        $this->markPaid($payment->order);
    }

    /**
     * Reject a manual payment (admin action).
     */
    public function rejectManualPayment(ManualPayment $payment, int $adminUserId, string $reason): void
    {
        DB::transaction(function () use ($payment, $adminUserId, $reason) {
            $payment->update([
                'status'           => 'rejected',
                'reviewed_by'      => $adminUserId,
                'reviewed_at'      => now(),
                'rejection_reason' => $reason,
            ]);
        });

        $this->markFailed($payment->order, "Manual payment rejected: {$reason}");
    }
}
