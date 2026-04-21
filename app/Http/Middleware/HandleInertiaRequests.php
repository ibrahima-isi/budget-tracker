<?php

namespace App\Http\Middleware;

use App\Models\Setting;
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
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? array_merge(
                    $request->user()->only('id', 'name', 'email'),
                    ['is_admin' => (bool) $request->user()->is_admin]
                ) : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
            'appSettings' => function () {
                // Cache within the request so the closure is only evaluated once
                // even when multiple Inertia partial-reload cycles share props.
                static $settings = null;
                $settings ??= Setting::instance();

                $data = $settings->only('business_name', 'business_email', 'phone', 'language', 'default_currency');
                $data['logo_url'] = $settings->logo_path ? route('logo') : null;
                return $data;
            },
        ];
    }
}
