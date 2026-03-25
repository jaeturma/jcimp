<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    /**
     * List all orders with optional filters.
     * Permission: manage orders
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('manage orders');   // Spatie gate auto-registered

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
     * Permission: manage orders
     */
    public function show(Order $order): OrderResource
    {
        $this->authorize('manage orders');

        $order->load(['items.ticket', 'manualPayment.reviewer', 'issuedTickets.ticket']);

        return new OrderResource($order);
    }
}
