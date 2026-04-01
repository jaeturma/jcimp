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
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\RoleManagementController;
use App\Http\Controllers\Admin\PermissionManagementController;
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
        ->middleware('throttle:30,1');
    Route::delete('/cart/reserve', [ReservationController::class, 'cancelCart']);

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
        ->middleware('throttle:30,1');

    // Quick buy: Step 1 — reserve, Step 2 — pay
    Route::post('/checkout/quick-reserve', [CheckoutController::class, 'quickReserve'])
        ->middleware('throttle:30,1');
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

    // ── Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:view dashboard');

    // ── Event Management (CRUD + cover image upload)
    Route::get('/events',                [AdminEventController::class, 'index'])
        ->middleware('permission:view events');
    Route::post('/events',               [AdminEventController::class, 'store'])
        ->middleware('permission:create events');
    Route::get('/events/{event}',        [AdminEventController::class, 'show'])
        ->middleware('permission:view events');
    Route::put('/events/{event}',        [AdminEventController::class, 'update'])
        ->middleware('permission:update events');
    Route::delete('/events/{event}',     [AdminEventController::class, 'destroy'])
        ->middleware('permission:delete events');
    Route::post('/events/{event}/cover', [AdminEventController::class, 'uploadCover'])
        ->middleware('permission:update events');

    // ── Ticket Management
    Route::get('/tickets',              [AdminTicketController::class, 'index'])
        ->middleware('permission:view tickets');
    Route::post('/tickets',             [AdminTicketController::class, 'store'])
        ->middleware('permission:create tickets');
    Route::put('/tickets/{ticket}',     [AdminTicketController::class, 'update'])
        ->middleware('permission:update tickets');
    Route::delete('/tickets/{ticket}',  [AdminTicketController::class, 'destroy'])
        ->middleware('permission:delete tickets');
    Route::get('/tickets/stats',        [AdminTicketController::class, 'stats'])
        ->middleware('permission:view tickets');
    Route::post('/tickets/{ticket}/qr', [AdminTicketController::class, 'uploadQr'])
        ->middleware('permission:update tickets');
    Route::delete('/tickets/{ticket}/qr', [AdminTicketController::class, 'removeQr'])
        ->middleware('permission:update tickets');
    Route::post('/tickets/{ticket}/secondary-qr', [AdminTicketController::class, 'uploadSecondaryQr'])
        ->middleware('permission:update tickets');
    Route::delete('/tickets/{ticket}/secondary-qr', [AdminTicketController::class, 'removeSecondaryQr'])
        ->middleware('permission:update tickets');
    Route::post('/tickets/{ticket}/image', [AdminTicketController::class, 'uploadTicketImage'])
        ->middleware('permission:update tickets');
    Route::delete('/tickets/{ticket}/image', [AdminTicketController::class, 'removeTicketImage'])
        ->middleware('permission:update tickets');

    // ── Order Management
    Route::get('/orders',                          [AdminOrderController::class, 'index'])
        ->middleware('permission:view orders');
    Route::post('/orders',                         [AdminOrderController::class, 'store'])
        ->middleware('permission:create orders');
    Route::post('/orders/direct-issue',            [AdminOrderController::class, 'directIssue'])
        ->middleware('permission:create orders');
    Route::get('/orders/{order}',                  [AdminOrderController::class, 'show'])
        ->middleware('permission:view orders');
    Route::put('/orders/{order}',                  [AdminOrderController::class, 'update'])
        ->middleware('permission:update orders');
    Route::delete('/orders/{order}',               [AdminOrderController::class, 'destroy'])
        ->middleware('permission:delete orders');
    Route::post('/orders/{order}/send-tickets',      [AdminOrderController::class, 'sendTickets'])
        ->middleware('permission:update orders');
    Route::post('/orders/{order}/regenerate-cards', [AdminOrderController::class, 'regenerateCards'])
        ->middleware('permission:update orders');

    // ── Manual Payment Review
    Route::get('/manual-payments',                         [ManualPaymentController::class, 'index'])
        ->middleware('permission:review manual payments');
    Route::get('/manual-payments/{manualPayment}',         [ManualPaymentController::class, 'show'])
        ->middleware('permission:review manual payments');
    Route::post('/manual-payments/{manualPayment}/review', [ManualPaymentController::class, 'review'])
        ->middleware('permission:review manual payments');

    // ── Student Verification
    Route::get('/student-verifications',                                       [AdminStudentVerificationController::class, 'index'])
        ->middleware('permission:verify students');
    Route::get('/student-verifications/{studentVerification}',                [AdminStudentVerificationController::class, 'show'])
        ->middleware('permission:verify students');
    Route::post('/student-verifications/{studentVerification}/review',        [AdminStudentVerificationController::class, 'review'])
        ->middleware('permission:verify students');

    // ── System Settings
    Route::post('/settings/recaptcha',   [SettingsController::class, 'updateRecaptcha'])
        ->middleware('permission:manage settings');
    Route::post('/settings/smtp',        [SettingsController::class, 'updateSmtp'])
        ->middleware('permission:manage settings');
    Route::post('/settings/test-email',  [SettingsController::class, 'testEmail'])
        ->middleware('permission:manage settings');

    // ── User Management
    Route::get('/users',         [UserManagementController::class, 'index'])
        ->middleware('permission:view users');
    Route::post('/users',        [UserManagementController::class, 'store'])
        ->middleware('permission:create users');
    Route::get('/users/{user}',  [UserManagementController::class, 'show'])
        ->middleware('permission:view users');
    Route::put('/users/{user}',  [UserManagementController::class, 'update'])
        ->middleware('permission:update users');
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])
        ->middleware('permission:delete users');

    // ── Role Management
    Route::get('/roles',         [RoleManagementController::class, 'index'])
        ->middleware('permission:view roles');
    Route::post('/roles',        [RoleManagementController::class, 'store'])
        ->middleware('permission:create roles');
    Route::get('/roles/{role}',  [RoleManagementController::class, 'show'])
        ->middleware('permission:view roles');
    Route::put('/roles/{role}',  [RoleManagementController::class, 'update'])
        ->middleware('permission:update roles');
    Route::delete('/roles/{role}', [RoleManagementController::class, 'destroy'])
        ->middleware('permission:delete roles');

    // ── Permission Management
    Route::get('/permissions', [PermissionManagementController::class, 'index'])
        ->middleware('permission:view permissions');
    Route::post('/permissions/assign', [PermissionManagementController::class, 'assign'])
        ->middleware('permission:assign permissions');
});

/*
|--------------------------------------------------------------------------
| Staff API — super_admin + admin + staff roles (scan tickets)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'staff'])->prefix('admin')->group(function () {
    Route::get('/scan/tickets', [ScannerController::class, 'listTickets'])
        ->middleware('permission:scan tickets');
    Route::post('/scan',        [ScannerController::class, 'scan'])
        ->middleware('permission:scan tickets');
    Route::get('/scan/stats',   [ScannerController::class, 'stats'])
        ->middleware('permission:scan tickets');
});
