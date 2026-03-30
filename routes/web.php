<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\ManualPaymentController as AdminManualPaymentController;
use App\Http\Controllers\Admin\StudentVerificationController as AdminStudentVerificationController;
use App\Models\Event;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    $event = Event::active()->first() ?? Event::first();
    return Inertia::render('Welcome', [
        'canLogin'    => Route::has('login'),
        'canRegister' => Route::has('register'),
        'event'       => $event ? [
            'id'          => $event->id,
            'name'        => $event->name,
            'description' => $event->description,
            'venue'       => $event->venue,
            'event_date'  => $event->event_date?->toISOString(),
            'cover_url'   => $event->cover_image ? asset('storage/' . $event->cover_image) : null,
        ] : null,
    ]);
});

/*
|--------------------------------------------------------------------------
| Authenticated user routes
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', function () {
    $user = auth()->user();
    return Inertia::render('Dashboard', [
        'isAdmin'     => $user?->isAdminOrAbove() ?? false,
        'isManager'   => $user?->isManager()      ?? false,
        'isValidator' => $user?->isValidator()    ?? false,
        'isStaff'     => $user?->isStaff()        ?? false,
        'userRole'    => $user?->getRoleNames()->first() ?? 'guest',
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Public ticket routes (no auth required)
|--------------------------------------------------------------------------
*/

Route::get('/tickets', function () {
    $event = Event::active()->first() ?? Event::first();
    return Inertia::render('Tickets/Index', [
        'eventId'    => $event?->id ?? 1,
        'eventName'  => $event?->name ?? 'Concert',
        'eventDesc'  => $event?->description ?? '',
        'eventVenue' => $event?->venue ?? '',
        'eventDate'  => $event?->event_date ?? '',
        'coverUrl'   => $event?->cover_image ? asset('storage/' . $event->cover_image) : null,
    ]);
})->name('tickets.index');

Route::get('/tickets/quick-buy/{ticket}', function (\App\Models\Ticket $ticket) {
    // Ensure ticket is available
    if ($ticket->availableQuantity() === 0) {
        abort(404, 'Ticket not available');
    }

    return Inertia::render('Tickets/QuickBuy', [
        'ticket' => [
            'id'    => $ticket->id,
            'name'  => $ticket->name,
            'price' => $ticket->price,
            'type'  => $ticket->type,
            'available' => $ticket->availableQuantity(),
            'requires_verification' => $ticket->requires_verification,
        ],
        'event' => [
            'id'    => $ticket->event->id,
            'name'  => $ticket->event->name,
            'venue' => $ticket->event->venue,
            'date'  => $ticket->event->event_date,
        ],
    ]);
})->name('tickets.quick-buy');

Route::get('/tickets/checkout', function () {
    // items[] and expiresAt come from the cart page after bulk reservation
    $items     = json_decode(request()->input('items', '[]'), true) ?? [];
    $expiresAt = request()->input('expires_at', '');

    return Inertia::render('Tickets/Checkout', [
        'cartItems' => $items,      // [{ticket_id, ticket_name, ticket_type, quantity, price}]
        'email'     => request()->input('email', ''),
        'expiresAt' => $expiresAt,
    ]);
})->name('tickets.checkout');

Route::get('/orders/{reference}', function (string $reference) {
    return Inertia::render('Tickets/OrderStatus', ['reference' => $reference]);
})->name('orders.status');

Route::get('/payment/qr-pending', function () {
    return Inertia::render('Tickets/OrderStatus', ['reference' => request('reference')]);
})->name('payment.qr-pending');

Route::get('/my-tickets', function () {
    return Inertia::render('Tickets/MyTickets');
})->name('my-tickets');

// Quick buy step 2: payment form (opened from email link)
Route::get('/pay/{token}', function (string $token) {
    return Inertia::render('Tickets/QuickPay', ['token' => $token]);
})->name('tickets.quick-pay');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Admin panel — super_admin + admin roles
|--------------------------------------------------------------------------
*/

// Signed route for admin to view student ID images (no CSRF needed, signed URL provides auth)
Route::get('/admin/student-id/{verification}', [AdminStudentVerificationController::class, 'viewImage'])
    ->middleware(['signed', 'auth', 'admin'])
    ->name('admin.student-id.view');

// Serve private payment proof images (session auth, same-origin requests from the admin SPA)
Route::get('/admin/proof/{manualPayment}', [AdminManualPaymentController::class, 'serveProof'])
    ->middleware(['auth', 'admin'])
    ->name('admin.proof');

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard',     fn () => Inertia::render('Admin/Dashboard'))->name('admin.dashboard');
    Route::get('/events',        fn () => Inertia::render('Admin/Events'))->name('admin.events');
    Route::get('/tickets',       fn () => Inertia::render('Admin/Tickets'))->name('admin.tickets');
    Route::get('/payments',      fn () => Inertia::render('Admin/Payments'))->name('admin.payments');
    Route::get('/settings', function () {
        return Inertia::render('Admin/Settings', [
            'smtpSettings' => [
                'mailer'       => config('mail.default', 'log'),
                'host'         => config('mail.mailers.smtp.host', ''),
                'port'         => config('mail.mailers.smtp.port', 587),
                'username'     => config('mail.mailers.smtp.username', ''),
                'scheme'       => config('mail.mailers.smtp.scheme', 'null'),
                'from_address' => config('mail.from.address', ''),
                'from_name'    => config('mail.from.name', ''),
                // password intentionally omitted
            ],
        ]);
    })->name('admin.settings');
    Route::get('/orders',        fn () => Inertia::render('Admin/Orders'))->name('admin.orders');
    Route::get('/verifications', fn () => Inertia::render('Admin/Verifications'))->name('admin.verifications');
    Route::get('/users',         fn () => Inertia::render('Admin/Users'))->name('admin.users');
    Route::get('/roles',         fn () => Inertia::render('Admin/Roles'))->name('admin.roles');
    Route::get('/permissions',   fn () => Inertia::render('Admin/Permissions'))->name('admin.permissions');
});

/*
|--------------------------------------------------------------------------
| Staff panel — all roles (super_admin, admin, staff)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'staff'])->prefix('admin')->group(function () {
    Route::get('/scanner', fn () => Inertia::render('Admin/Scanner'))->name('admin.scanner');
});

require __DIR__.'/auth.php';
