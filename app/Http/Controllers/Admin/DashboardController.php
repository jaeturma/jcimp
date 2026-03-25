<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ManualPayment;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('view dashboard');

        $totalRevenue = Order::where('status', 'paid')->sum('total_amount');

        $ticketStats = Ticket::selectRaw('
                sum(sold_quantity) as total_sold,
                sum(reserved_quantity) as total_reserved,
                sum(total_quantity) as total_capacity
            ')->first();

        $pendingPayments = ManualPayment::where('status', 'pending')->count();
        $activeReservations = Reservation::active()->count();

        $recentOrders = Order::with('items.ticket')
            ->latest()
            ->limit(10)
            ->get(['id', 'reference', 'email', 'status', 'total_amount', 'created_at']);

        return response()->json([
            'total_revenue'      => number_format($totalRevenue, 2),
            'total_sold'         => $ticketStats->total_sold ?? 0,
            'total_reserved'     => $ticketStats->total_reserved ?? 0,
            'total_capacity'     => $ticketStats->total_capacity ?? 0,
            'pending_payments'   => $pendingPayments,
            'active_reservations'=> $activeReservations,
            'recent_orders'      => $recentOrders,
        ]);
    }
}
