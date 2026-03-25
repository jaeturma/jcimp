<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\GuestStudentController;
use App\Http\Controllers\MyTicketsController;
use App\Http\Controllers\PaymentOtpController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\TicketTransferController;
use App\Http\Controllers\StudentVerificationController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\ManualPaymentController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ScannerController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StudentVerificationController as AdminStudentVerificationController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API — Ticket selection & reservation
|--------------------------------------------------------------------------
*/

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);

    Route::get('/my-tickets', [MyTicketsController::class, 'index']);

    // Issued ticket management (transfer, assign, resell)
    Route::get('/my-issued-tickets',                         [TicketTransferController::class, 'index']);
    Route::get('/resale-tickets',                            [TicketTransferController::class, 'resaleMarket']);
    Route::patch('/tickets-issued/{qr}/assign',              [TicketTransferController::class, 'assign'])->middleware('throttle:10,1');
    Route::post('/tickets-issued/{qr}/transfer',             [TicketTransferController::class, 'transfer'])->middleware('throttle:5,1');
    Route::post('/tickets-issued/{qr}/resell',               [TicketTransferController::class, 'listResale']);
    Route::delete('/tickets-issued/{qr}/resell',             [TicketTransferController::class, 'cancelResale']);

    // Bulk cart reservation — reserves all items in one call (all-or-nothing)
    Route::post('/cart/reserve', [ReservationController::class, 'reserveCart'])
        ->middleware('throttle:10,1');

    // Legacy single-ticket reservation (kept for compatibility)
    Route::post('/reservations', [ReservationController::class, 'store'])
        ->middleware('throttle:10,1');
    Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy']);

    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::post('/checkout/send-otp', [PaymentOtpController::class, 'send'])
        ->middleware('throttle:5,1');
    Route::post('/checkout/verify-otp', [PaymentOtpController::class, 'verify'])
        ->middleware('throttle:10,1');
    Route::post('/checkout/proof', [CheckoutController::class, 'uploadProof'])
        ->middleware('throttle:5,1');

    // Quick buy: Step 1 — reserve, Step 2 — pay
    Route::post('/checkout/quick-reserve', [CheckoutController::class, 'quickReserve'])
        ->middleware('throttle:5,1');
    Route::get('/checkout/quick-pay/{token}',  [CheckoutController::class, 'quickPayInfo']);
    Route::post('/checkout/quick-pay/{token}', [CheckoutController::class, 'quickPay'])
        ->middleware('throttle:5,1');
    Route::get('/checkout/{reference}/status', [CheckoutController::class, 'status']);

    // Student verification status (available to guests)
    Route::get('/student-verification/status', [StudentVerificationController::class, 'status']);

    // Student verification (authenticated users)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/student-verification', [StudentVerificationController::class, 'store']);
    });

    // Guest student OTP verification flow
    Route::post('/student/send-otp',       [GuestStudentController::class, 'sendOtp'])->middleware('throttle:5,1');
    Route::post('/student/verify-otp',     [GuestStudentController::class, 'verifyOtp'])->middleware('throttle:10,1');
    Route::post('/student/submit-details', [GuestStudentController::class, 'submitDetails'])->middleware('throttle:5,1');
    Route::get('/student/check-status',    [GuestStudentController::class, 'checkStatus']);
});

/*
|--------------------------------------------------------------------------
| Webhook — no CSRF, no throttle
|--------------------------------------------------------------------------
*/

Route::post('/webhooks/payment', [WebhookController::class, 'handle'])
    ->withoutMiddleware(['throttle:api']);

/*
|--------------------------------------------------------------------------
| Admin API — super_admin + admin roles
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {

    // ── Dashboard (permission: view dashboard) ────────────────────────────
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // ── Event Management (CRUD + cover image upload) ─────────────────────
    Route::get('/events',                    [AdminEventController::class, 'index']);
    Route::post('/events',                   [AdminEventController::class, 'store']);
    Route::get('/events/{event}',            [AdminEventController::class, 'show']);
    Route::put('/events/{event}',            [AdminEventController::class, 'update']);
    Route::delete('/events/{event}',         [AdminEventController::class, 'destroy']);
    Route::post('/events/{event}/cover',     [AdminEventController::class, 'uploadCover']);

    // ── Ticket Management (permission: manage tickets) ────────────────────
    Route::get('/tickets',                    [AdminTicketController::class, 'index']);
    Route::post('/tickets',                   [AdminTicketController::class, 'store']);
    Route::put('/tickets/{ticket}',           [AdminTicketController::class, 'update']);
    Route::delete('/tickets/{ticket}',        [AdminTicketController::class, 'destroy']);
    Route::get('/tickets/stats',              [AdminTicketController::class, 'stats']);
    Route::post('/tickets/{ticket}/qr',       [AdminTicketController::class, 'uploadQr']);
    Route::delete('/tickets/{ticket}/qr',     [AdminTicketController::class, 'removeQr']);

    // ── Order Management (permission: manage orders) ──────────────────────
    Route::get('/orders',         [AdminOrderController::class, 'index']);
    Route::get('/orders/{order}', [AdminOrderController::class, 'show']);

    // ── Manual Payment Review (permission: review manual payments) ────────
    Route::get('/manual-payments',                          [ManualPaymentController::class, 'index']);
    Route::get('/manual-payments/{manualPayment}',          [ManualPaymentController::class, 'show']);
    Route::post('/manual-payments/{manualPayment}/review',  [ManualPaymentController::class, 'review']);

    // ── Student Verification (permission: verify students) ────────────────
    Route::get('/student-verifications',                                    [AdminStudentVerificationController::class, 'index']);
    Route::get('/student-verifications/{studentVerification}',              [AdminStudentVerificationController::class, 'show']);
    Route::post('/student-verifications/{studentVerification}/review',      [AdminStudentVerificationController::class, 'review']);

    // ── System Settings
    Route::post('/settings/recaptcha',   [SettingsController::class, 'updateRecaptcha']);
    Route::post('/settings/smtp',        [SettingsController::class, 'updateSmtp']);
    Route::post('/settings/test-email',  [SettingsController::class, 'testEmail']);
});

/*
|--------------------------------------------------------------------------
| Staff API — super_admin + admin + staff roles (scan tickets)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'staff'])->prefix('admin')->group(function () {
    Route::post('/scan',   [ScannerController::class, 'scan']);
    Route::get('/scan/stats', [ScannerController::class, 'stats']);
});
