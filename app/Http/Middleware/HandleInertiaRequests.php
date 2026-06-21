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
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'flash' => [
                'status' => $request->session()->get('status'),
                'complianceWarnings' => $request->session()->get('compliance_warnings'),
            ],
            // In-app de SLA: só admin/manager recebem SlaAlertRaised (ver EvaluateSlaForLeadAction).
            'unreadNotifications' => $user
                ? $user->unreadNotifications()->latest()->limit(10)->get(['id', 'data', 'created_at'])
                : [],
            // Compartilhado também para guest (login/registro) — ver Admin/Appearance.
            'theme' => [
                'primary' => Setting::get('theme.primary_color', '#4F46E5'),
                'secondary' => Setting::get('theme.secondary_color', '#06B6D4'),
            ],
        ];
    }
}
