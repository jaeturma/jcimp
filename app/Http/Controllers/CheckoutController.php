<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\SubmitManualPaymentRequest;
use App\Http\Requests\UploadPaymentProofRequest;
use App\Mail\QuickReserveMail;
use App\Models\Order;
use App\Models\QuickReservation;
use App\Models\Reservation;
use App\Models\Ticket;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly PaymentService  $paymentService,
    ) {}

    /**
     * Convert a cart into a pending order.
     *
     * POST /api/checkout
     * Body: { items: [{ticket_id, quantity}, ...], email, payment_method }
     *
     * The service locates active reservations internally — no reservation IDs
     * are required in the request.
     *
     * Response (spec format):
     *   { order_id, order_reference, amount, payment_method }
     *   + payment_url when method = qrph
     */
    public function store(CheckoutRequest $request): JsonResponse
    {
        try {
            $result = $this->checkoutService->checkout(
                cartItems:     $request->input('items'),
                email:         $request->string('email')->value(),
                paymentMethod: $request->string('payment_method')->value(),
                user:          $request->user(),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // For QR Ph, generate the payment gateway URL
        if ($request->string('payment_method') === 'qrph') {
            $order = Order::find($result['order_id']);
            $result['payment_url'] = $this->paymentService->initiateQrPh($order);
        }

        return response()->json($result, 201);
    }

    /**
     * Upload manual payment proof with automatic OCR extraction.
     *
     * POST /api/checkout/proof
     * Body: { order_reference, proof_image, transaction_number?, transaction_amount? }
     *
     * If transaction details are not provided, the system will attempt to extract
     * them automatically using OCR (Optical Character Recognition).
     *
     * Response includes:
     * - OCR extraction result
     * - Confidence score
     * - Extracted transaction details (if successful)
     */
    public function uploadProof(SubmitManualPaymentRequest $request): JsonResponse
    {
        $order = Order::where('reference', $request->string('order_reference'))
            ->where('payment_method', 'manual')
            ->firstOrFail();

        if (! $order->isPending()) {
            return response()->json(['message' => 'Order cannot accept a proof at this stage.'], 422);
        }

        // Check that OTP was verified (order status should be 'otp_verified')
        if ($order->status !== 'otp_verified' && $order->status !== 'pending') {
            return response()->json([
                'message' => 'Please verify your OTP before submitting payment proof.',
            ], 422);
        }

        $manualPayment = $this->paymentService->submitManualProof(
            order:               $order,
            file:                $request->file('proof_image'),
            transactionNumber:   $request->string('transaction_number')->value(),
            transactionAmount:   $request->string('transaction_amount')->value()
        );

        // Prepare response with OCR results
        $response = [
            'message' => 'Payment proof submitted successfully. Awaiting admin review.',
            'order_id' => $order->id,
            'manual_payment_id' => $manualPayment->id,
            'ocr_extraction' => [
                'extracted' => $manualPayment->ocr_extracted,
                'confidence' => $manualPayment->ocr_confidence,
                'transaction_number' => $manualPayment->transaction_number,
                'transaction_amount' => $manualPayment->transaction_amount ? (float) $manualPayment->transaction_amount : null,
            ],
        ];

        // If OCR confidence is low, warn the user that manual verification may be needed
        if ($manualPayment->ocr_extracted && $manualPayment->ocr_confidence && $manualPayment->ocr_confidence < 75) {
            $response['warning'] = 'OCR extraction had low confidence. Admin will verify the transaction details.';
        }

        return response()->json($response);
    }

    /**
     * Get order status (polled by the frontend after payment).
     *
     * GET /api/checkout/{reference}/status
     */
    public function status(string $reference): JsonResponse
    {
        $order = Order::with(['items.ticket', 'issuedTickets.ticket'])
            ->where('reference', $reference)
            ->firstOrFail();

        $data = [
            'reference'      => $order->reference,
            'email'          => $order->email,
            'status'         => $order->status,
            'payment_method' => $order->payment_method,
            'total_amount'   => (float) $order->total_amount,
            'paid'           => $order->isPaid(),
            'items'          => $order->items->map(fn ($item) => [
                'ticket_name' => $item->ticket?->name,
                'ticket_type' => $item->ticket?->type,
                'quantity'    => $item->quantity,
                'price'       => (float) $item->price,
                'subtotal'    => $item->subtotal(),
            ]),
            'tickets_issued' => [],
        ];

        if ($order->isPaid()) {
            $data['tickets_issued'] = $order->issuedTickets->map(fn ($t) => [
                'qr_code' => $t->qr_code,
                'status'  => $t->status,
                'ticket'  => ['name' => $t->ticket?->name, 'type' => $t->ticket?->type],
            ]);
        }

        return response()->json($data);
    }

    /**
     * Step 1 — Reserve a single ticket (no payment yet).
     *
     * POST /api/checkout/quick-reserve
     * Fields: ticket_id, email, want_register (bool), g_recaptcha_token
     *
     * Creates a 10-minute reservation and emails the user a payment link.
     */
    public function quickReserve(Request $request): JsonResponse
    {
        $request->validate([
            'ticket_id'          => 'required|integer|exists:tickets,id',
            'email'              => 'required|email|max:255',
            'want_register'      => 'boolean',
            'g_recaptcha_token'  => 'nullable|string',
        ]);

        // ── reCAPTCHA verification ──────────────────────────────────────────
        $secretKey = config('services.recaptcha.secret_key');
        if ($secretKey) {
            $token = $request->input('g_recaptcha_token');
            if (! $token) {
                return response()->json(['message' => 'Please complete the reCAPTCHA challenge.'], 422);
            }

            $verify = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret'   => $secretKey,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

            if (! ($verify->json('success') ?? false)) {
                return response()->json(['message' => 'reCAPTCHA verification failed. Please try again.'], 422);
            }
        }

        $ticket = Ticket::with('event')->findOrFail($request->ticket_id);

        if ($ticket->availableQuantity() < 1) {
            return response()->json(['message' => 'Ticket is no longer available.'], 422);
        }

        if ($ticket->type === 'student' && $ticket->requires_verification) {
            return response()->json(['message' => 'Student tickets require verification. Please register an account first.'], 422);
        }

        $qr = QuickReservation::create([
            'token'         => Str::uuid()->toString(),
            'ticket_id'     => $ticket->id,
            'email'         => $request->string('email')->lower()->value(),
            'want_register' => $request->boolean('want_register'),
            'expires_at'    => now()->addMinutes(10),
        ]);

        Mail::to($qr->email)->send(new QuickReserveMail($qr, $ticket));

        return response()->json([
            'message'    => 'Reservation confirmed. Check your email for the payment link.',
            'expires_at' => $qr->expires_at->toISOString(),
        ], 201);
    }

    /**
     * Step 1b — Return reservation info for the payment page.
     *
     * GET /api/checkout/quick-pay/{token}
     */
    public function quickPayInfo(string $token): JsonResponse
    {
        $qr = QuickReservation::with('ticket.event')
            ->where('token', $token)
            ->whereNull('used_at')
            ->first();

        if (! $qr) {
            return response()->json(['message' => 'Reservation not found or already used.'], 404);
        }

        if ($qr->isExpired()) {
            return response()->json(['message' => 'This reservation has expired.'], 410);
        }

        $ticket = $qr->ticket;
        $event  = $ticket->event;

        return response()->json([
            'email'      => $qr->email,
            'expires_at' => $qr->expires_at->toISOString(),
            'ticket' => [
                'name'        => $ticket->name,
                'price'       => (float) $ticket->price,
                'type'        => $ticket->type,
                'gcash_qr_url' => $ticket->gcash_qr
                    ? Storage::disk('public')->url($ticket->gcash_qr)
                    : null,
            ],
            'event' => [
                'name'  => $event->name ?? '',
                'venue' => $event->venue ?? '',
                'date'  => $event->event_date?->toISOString() ?? '',
            ],
        ]);
    }

    /**
     * Step 2 — Submit payment proof to complete the reservation.
     *
     * POST /api/checkout/quick-pay/{token}   (multipart/form-data)
     * Fields: proof_image
     */
    public function quickPay(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'proof_image' => 'required|file|mimes:jpg,jpeg,png,pdf,webp|max:5120',
        ]);

        $qr = QuickReservation::with('ticket')
            ->where('token', $token)
            ->whereNull('used_at')
            ->first();

        if (! $qr) {
            return response()->json(['message' => 'Reservation not found or already used.'], 404);
        }

        if ($qr->isExpired()) {
            return response()->json(['message' => 'Reservation has expired. Please start over.'], 410);
        }

        $ticket = $qr->ticket;

        if ($ticket->availableQuantity() < 1) {
            return response()->json(['message' => 'Ticket is no longer available.'], 422);
        }

        // Build a transient Reservation for the payment service
        $reservation = new Reservation([
            'ticket_id' => $ticket->id,
            'quantity'  => 1,
        ]);
        $reservation->setRelation('ticket', $ticket);

        try {
            $order = $this->paymentService->createOrder(
                reservations: collect([$reservation]),
                email:         $qr->email,
                paymentMethod: 'manual',
            );

            $this->paymentService->submitManualProof($order, $request->file('proof_image'));

            // Mark reservation as used
            $qr->update(['used_at' => now()]);

            // Optionally register the user
            if ($qr->want_register) {
                $this->registerUserIfNotExists($qr->email);
            }

            return response()->json([
                'order_reference' => $order->reference,
                'message'         => 'Payment proof submitted. Awaiting admin review.',
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Create a user account if one does not already exist for this email.
     * Sends a password-reset link so the user can set their own password.
     */
    private function registerUserIfNotExists(string $email): void
    {
        if (\App\Models\User::where('email', $email)->exists()) {
            return;
        }

        \App\Models\User::create([
            'name'     => ucfirst(explode('@', $email)[0]),
            'email'    => $email,
            'password' => Hash::make(Str::random(32)),
        ]);

        Password::sendResetLink(['email' => $email]);
    }
}

