<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Mail\TicketIssuedMail;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /**
     * List all orders with optional filters.
     * Permission: view orders
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        if (!auth()->user()->hasRole('super_admin')) {
            $this->authorize('view orders');
        }

        $orders = Order::with(['items.ticket', 'manualPayment'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('payment_method'), fn ($q) => $q->where('payment_method', $request->payment_method))
            ->when($request->filled('search'), fn ($q) => $q
                ->where('reference', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%")
            )
            ->latest()
            ->paginate(intval($request->input('per_page', 20)) > 0 ? intval($request->input('per_page', 20)) : 20);

        return OrderResource::collection($orders);
    }

    /**
     * Show a single order with full detail.
     * Permission: view orders
     */
    public function show(Order $order): OrderResource
    {
        if (!auth()->user()->hasRole('super_admin')) {
            $this->authorize('view orders');
        }

        $order->load(['items.ticket', 'manualPayment.reviewer', 'issuedTickets.ticket']);

        return new OrderResource($order);
    }

    /**
     * Create a new order.
     * Permission: create orders
     */
    public function store(Request $request): OrderResource
    {
        if (!auth()->user()->hasRole('super_admin')) {
            $this->authorize('create orders');
        }

        $validated = $request->validate([
            'email' => 'required|email',
            'status' => ['required', Rule::in(['pending', 'pending_verification', 'paid', 'failed'])],
            'payment_method' => ['required', Rule::in(['qrph', 'manual'])],
            'total_amount' => 'required|numeric|min:0',
            'reference' => 'nullable|string',
        ]);

        $order = Order::create($validated);

        return new OrderResource($order);
    }

    /**
     * Update an order.
     * Permission: update orders
     */
    public function update(Request $request, Order $order): OrderResource
    {
        if (!auth()->user()->hasRole('super_admin')) {
            $this->authorize('update orders');
        }

        $validated = $request->validate([
            'email' => 'sometimes|email',
            'status' => ['sometimes', Rule::in(['pending', 'pending_verification', 'paid', 'failed'])],
            'payment_method' => ['sometimes', Rule::in(['qrph', 'manual'])],
            'total_amount' => 'sometimes|numeric|min:0',
            'reference' => 'nullable|string',
        ]);

        $order->update($validated);

        return new OrderResource($order);
    }

    /**
     * Delete an order.
     * Permission: delete orders
     */
    public function destroy(Order $order): JsonResponse
    {
        if (!auth()->user()->hasRole('super_admin')) {
            $this->authorize('delete orders');
        }

        $order->delete();

        return response()->json(['message' => 'Order deleted successfully.']);
    }

    /**
     * Directly issue tickets for a walk-in / cash payment.
     * Creates an order, marks it paid, generates and emails tickets immediately.
     * Permission: create orders
     */
    public function directIssue(Request $request, PaymentService $paymentService): OrderResource
    {
        if (!auth()->user()->hasRole('super_admin')) {
            $this->authorize('create orders');
        }

        $validated = $request->validate([
            'email'              => 'required|email',
            'payment_method'     => ['required', Rule::in(['cash', 'gcash', 'paymaya', 'gotyme', 'manual', 'qrph'])],
            'reference_no'       => 'nullable|string|max:100',
            'items'              => 'required|array|min:1',
            'items.*.ticket_id'  => 'required|integer|exists:tickets,id',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        $order = $paymentService->directIssue(
            $validated['email'],
            $validated['items'],
            $validated['payment_method'],
            $validated['reference_no'] ?? null,
        );

        $order->load(['items.ticket', 'issuedTickets.ticket']);

        return new OrderResource($order);
    }

    /**
     * Send (or resend) ticket email for a paid order.
     * Optionally override the recipient email.
     * Permission: update orders
     */
    public function sendTickets(Request $request, Order $order): JsonResponse
    {
        if (!auth()->user()->hasRole('super_admin')) {
            $this->authorize('update orders');
        }

        if (!$order->isPaid()) {
            return response()->json(['message' => 'Order is not paid. Tickets can only be sent for paid orders.'], 422);
        }

        $validated = $request->validate([
            'email' => 'nullable|email',
        ]);

        $order->loadMissing(['items.ticket.event', 'issuedTickets.ticket']);

        if ($order->issuedTickets->isEmpty()) {
            return response()->json(['message' => 'No tickets have been issued for this order yet.'], 422);
        }

        $recipient = $validated['email'] ?? $order->email;

        Mail::to($recipient)->send(new TicketIssuedMail($order));

        return response()->json(['message' => "Tickets sent to {$recipient} successfully."]);
    }
}
