<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewPaymentRequest;
use App\Models\ManualPayment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ManualPaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
        $this->middleware('auth');
        $this->middleware('admin');
    }

    /**
     * List all manual payment submissions.
     */
    public function index(Request $request): JsonResponse
    {
        $payments = ManualPayment::with(['order'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20);

        return response()->json($payments);
    }

    /**
     * Show a single payment proof.
     */
    public function show(ManualPayment $manualPayment): JsonResponse
    {
        return response()->json([
            'payment'   => $manualPayment->load(['order.items.ticket']),
            'proof_url' => route('admin.proof', $manualPayment->id),
        ]);
    }

    /**
     * Stream the payment proof image (used directly by the frontend img tag).
     */
    public function serveProof(ManualPayment $manualPayment): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless(Storage::disk('private')->exists($manualPayment->proof_image), 404);

        return Storage::disk('private')->response($manualPayment->proof_image);
    }

    /**
     * Approve or reject a manual payment.
     */
    public function review(ReviewPaymentRequest $request, ManualPayment $manualPayment): JsonResponse
    {
        if (! $manualPayment->isPending()) {
            return response()->json(['message' => 'This payment has already been reviewed.'], 422);
        }

        if ($request->string('action') === 'approve') {
            $this->paymentService->approveManualPayment($manualPayment, $request->user()->id);
            return response()->json(['message' => 'Payment approved. Tickets will be generated.']);
        }

        $this->paymentService->rejectManualPayment(
            $manualPayment,
            $request->user()->id,
            $request->string('rejection_reason')
        );

        return response()->json(['message' => 'Payment rejected.']);
    }
}
