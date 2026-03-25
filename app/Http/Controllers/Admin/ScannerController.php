<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketIssued;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScannerController extends Controller
{
    /**
     * Validate and redeem a QR code at the venue entrance.
     * Permission: scan tickets
     */
    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'qr_code' => ['required', 'string'],
        ]);

        $issued = TicketIssued::with(['ticket', 'order'])
            ->where('qr_code', $request->qr_code)
            ->first();

        // Unknown QR code
        if (! $issued) {
            return response()->json([
                'valid'   => false,
                'status'  => 'invalid',
                'message' => 'QR code not found. This ticket does not exist.',
            ], 404);
        }

        // Already scanned
        if ($issued->isUsed()) {
            return response()->json([
                'valid'    => false,
                'status'   => 'already_used',
                'message'  => 'This ticket has already been used.',
                'used_at'  => $issued->used_at?->toISOString(),
                'ticket'   => $issued->ticket->name,
                'order'    => $issued->order->reference,
            ], 422);
        }

        // Order must be paid
        if (! $issued->order->isPaid()) {
            return response()->json([
                'valid'   => false,
                'status'  => 'unpaid',
                'message' => 'This ticket belongs to an unpaid order.',
                'order'   => $issued->order->reference,
            ], 422);
        }

        // ✅ Valid — mark as used
        $issued->markUsed();

        return response()->json([
            'valid'   => true,
            'status'  => 'admitted',
            'message' => 'Ticket valid. Entry granted.',
            'ticket'  => [
                'name'     => $issued->ticket->name,
                'type'     => $issued->ticket->type,
                'order'    => $issued->order->reference,
                'email'    => $issued->order->email,
                'used_at'  => $issued->used_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Get scan statistics for the current session.
     * Permission: scan tickets
     */
    public function stats(): JsonResponse
    {
        $stats = TicketIssued::selectRaw("
            COUNT(*) as total_issued,
            SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as total_used,
            SUM(CASE WHEN status = 'valid' THEN 1 ELSE 0 END) as total_remaining
        ")->first();

        return response()->json([
            'total_issued'    => (int) $stats->total_issued,
            'total_admitted'  => (int) $stats->total_used,
            'total_remaining' => (int) $stats->total_remaining,
            'admission_rate'  => $stats->total_issued > 0
                ? round(($stats->total_used / $stats->total_issued) * 100, 1)
                : 0,
        ]);
    }
}
