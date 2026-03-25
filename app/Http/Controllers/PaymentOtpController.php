<?php

namespace App\Http\Controllers;

use App\Mail\PaymentOtpMail;
use App\Models\Order;
use App\Models\PaymentOtp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PaymentOtpController extends Controller
{
    /**
     * Send OTP to email before payment.
     *
     * POST /api/checkout/send-otp
     * Body: { order_reference }
     *
     * Generates a 6-digit OTP valid for 10 minutes.
     * Sends via PaymentOtpMail.
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'order_reference' => ['required', 'string', 'exists:orders,reference'],
        ]);

        $order = Order::where('reference', $request->string('order_reference'))
            ->where('status', 'pending')
            ->firstOrFail();

        $email = $order->email;

        // Expire any existing unverified OTPs for this order
        PaymentOtp::where('email', $email)
            ->whereNull('verified_at')
            ->delete();

        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Create OTP record
        $paymentOtp = PaymentOtp::create([
            'email'      => $email,
            'token'      => Str::uuid()->toString(),
            'otp_hash'   => Hash::make($otp),
            'expires_at' => now()->addMinutes(10),
        ]);

        // Send OTP email
        Mail::to($email)->send(new PaymentOtpMail($otp, $order->reference));

        return response()->json([
            'message'  => "OTP sent to {$email}. Check your inbox.",
            'token'    => $paymentOtp->token,
            'expires_in_seconds' => 600,
        ]);
    }

    /**
     * Verify OTP before proceeding to payment.
     *
     * POST /api/checkout/verify-otp
     * Body: { order_reference, otp_token, otp_code }
     *
     * Validates OTP and marks payment as ready for proof upload.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'order_reference' => ['required', 'string', 'exists:orders,reference'],
            'otp_token'       => ['required', 'string'],
            'otp_code'        => ['required', 'string', 'size:6'],
        ]);

        $order = Order::where('reference', $request->string('order_reference'))->firstOrFail();

        $paymentOtp = PaymentOtp::where('email', $order->email)
            ->where('token', $request->string('otp_token'))
            ->whereNull('verified_at')
            ->first();

        if (!$paymentOtp) {
            return response()->json([
                'message' => 'OTP token not found or already used.',
            ], 422);
        }

        if ($paymentOtp->isExpired()) {
            $paymentOtp->delete();
            return response()->json([
                'message' => 'OTP has expired. Please request a new one.',
            ], 422);
        }

        if (!Hash::check($request->string('otp_code'), $paymentOtp->otp_hash)) {
            return response()->json([
                'message' => 'Incorrect OTP code. Please try again.',
            ], 422);
        }

        // Mark OTP as verified
        $paymentOtp->update(['verified_at' => now()]);

        // Update order status to OTP verified
        $order->update(['status' => 'otp_verified']);

        return response()->json([
            'message'  => 'OTP verified successfully. You may now upload payment proof.',
            'order_id' => $order->id,
        ]);
    }
}
