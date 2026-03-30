<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $can = $user
            ? $user->getAllPermissions()->pluck('name')
                ->mapWithKeys(fn ($p) => [$p => true])
                ->toArray()
            : [];

        return [
            ...parent::share($request),
            'auth' => [
                'user'       => $user,
                'can'        => $can,
                'isAdmin'    => $user?->isAdminOrAbove() ?? false,
                'isManager'  => $user?->isManager() ?? false,
                'isValidator'=> $user?->isValidator() ?? false,
                'isStaff'    => $user?->isStaff() ?? false,
                'hasOperatorAccess' => $user?->hasOperatorAccess() ?? false,
                'userRole'   => $user?->getRoleNames()->first() ?? 'guest',
            ],
            'recaptchaSiteKey' => config('services.recaptcha.site_key'),
        ];
    }
}
