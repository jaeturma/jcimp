<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    /**
     * Handle QR Ph payment gateway webhook (PayMongo / Xendit).
     * Always returns 200 to acknowledge receipt.
     */
    public function handle(Request $request): Response
    {
        // --- Signature verification ---
        if (! $this->isValidSignature($request)) {
            Log::warning('Webhook: invalid signature received.', ['ip' => $request->ip()]);
            return response('Unauthorized', 401);
        }

        $payload = $request->json()->all();
        Log::info('Webhook payload received.', ['type' => $payload['type'] ?? 'unknown']);

        // Adapt these keys to your actual gateway (PayMongo / Xendit)
        $type      = $payload['type'] ?? '';
        $gatewayRef = $payload['data']['attributes']['id'] ?? $payload['data']['id'] ?? null;
        $status     = $payload['data']['attributes']['status'] ?? null;

        if (! $gatewayRef) {
            Log::warning('Webhook: missing gateway reference in payload.');
            return response('OK', 200);
        }

        $order = Order::where('gateway_reference', $gatewayRef)->first();

        if (! $order) {
            Log::warning("Webhook: no order found for gateway ref {$gatewayRef}.");
            return response('OK', 200);
        }

        match (true) {
            in_array($type, ['payment.paid', 'source.chargeable']) || $status === 'paid' => $this->paymentService->markPaid($order),
            in_array($type, ['payment.failed'])                                          => $this->paymentService->markFailed($order, 'Gateway reported failure'),
            default                                                                      => Log::info("Webhook: unhandled type '{$type}' for order {$order->reference}."),
        };

        return response('OK', 200);
    }

    private function isValidSignature(Request $request): bool
    {
        $secret    = config('services.payment_gateway.webhook_secret');
        $signature = $request->header('X-Webhook-Signature') ?? $request->header('Paymongo-Signature');

        if (! $secret || ! $signature) {
            // In local dev with no secret configured, allow through
            return app()->isLocal();
        }

        $computedHmac = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($computedHmac, $signature);
    }
}
