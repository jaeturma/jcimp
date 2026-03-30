<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ManualPayment;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\StudentVerification;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        if (! auth()->user()->hasAnyRole(['super_admin', 'admin', 'manager'])) {
            abort(403, 'Access denied.');
        }

        // ── Revenue ───────────────────────────────────────────────────────────
        $totalRevenue = Order::where('status', 'paid')->sum('total_amount');

        // ── Ticket totals ─────────────────────────────────────────────────────
        $ticketStats = Ticket::selectRaw('
                sum(sold_quantity)     as total_sold,
                sum(reserved_quantity) as total_reserved,
                sum(total_quantity)    as total_capacity
            ')->first();

        // ── Order counts by status ────────────────────────────────────────────
        $orderCounts = Order::selectRaw("
                COUNT(*)                                                          AS total_orders,
                SUM(CASE WHEN status = 'paid'                THEN 1 ELSE 0 END)  AS paid_orders,
                SUM(CASE WHEN status IN ('pending','pending_verification')
                                                             THEN 1 ELSE 0 END)  AS pending_orders,
                SUM(CASE WHEN status = 'failed'              THEN 1 ELSE 0 END)  AS failed_orders
            ")->first();

        // ── Action-required counts ────────────────────────────────────────────
        $pendingPayments      = ManualPayment::where('status', 'pending')->count();
        $pendingVerifications = StudentVerification::where('status', 'pending')->count();
        $activeReservations   = Reservation::active()->count();

        // ── Per-tier breakdown ────────────────────────────────────────────────
        $ticketBreakdown = Ticket::with('event')->get()->map(fn (Ticket $t) => [
            'name'       => $t->name,
            'type'       => $t->type,
            'event_name' => $t->event?->name,
            'sold'       => (int) $t->sold_quantity,
            'reserved'   => (int) $t->reserved_quantity,
            'available'  => $t->availableQuantity(),
            'capacity'   => (int) $t->total_quantity,
            'revenue'    => number_format($t->sold_quantity * $t->price, 2),
            'fill_pct'   => $t->total_quantity > 0
                ? round(($t->sold_quantity / $t->total_quantity) * 100, 1)
                : 0,
        ]);

        // ── Recent orders ─────────────────────────────────────────────────────
        $recentOrders = Order::latest()
            ->limit(10)
            ->get(['id', 'reference', 'email', 'status', 'total_amount', 'created_at']);

        return response()->json([
            // Revenue
            'total_revenue'        => number_format($totalRevenue, 2),
            // Ticket totals
            'total_sold'           => (int) ($ticketStats->total_sold     ?? 0),
            'total_reserved'       => (int) ($ticketStats->total_reserved ?? 0),
            'total_capacity'       => (int) ($ticketStats->total_capacity ?? 0),
            // Order breakdown
            'total_orders'         => (int) ($orderCounts->total_orders   ?? 0),
            'paid_orders'          => (int) ($orderCounts->paid_orders    ?? 0),
            'pending_orders'       => (int) ($orderCounts->pending_orders ?? 0),
            'failed_orders'        => (int) ($orderCounts->failed_orders  ?? 0),
            // Action-required
            'pending_payments'     => $pendingPayments,
            'pending_verifications'=> $pendingVerifications,
            'active_reservations'  => $activeReservations,
            // Detail
            'ticket_breakdown'     => $ticketBreakdown,
            'recent_orders'        => $recentOrders,
        ]);
    }
}
