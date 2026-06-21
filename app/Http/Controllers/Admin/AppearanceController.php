<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAppearanceRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tela "Aparência" (admin apenas, ver Gate `manage-appearance`) — paleta Primary/Secondary
 * compartilhada com toda a aplicação via `theme` em HandleInertiaRequests::share().
 */
class AppearanceController extends Controller
{
    private const DEFAULTS = [
        'primary_color' => '#4F46E5',
        'secondary_color' => '#06B6D4',
    ];

    public function edit(): Response
    {
        Gate::authorize('manage-appearance');

        return Inertia::render('Admin/Appearance/Index', [
            'current' => [
                'primary_color' => Setting::get('theme.primary_color', self::DEFAULTS['primary_color']),
                'secondary_color' => Setting::get('theme.secondary_color', self::DEFAULTS['secondary_color']),
            ],
            'defaults' => self::DEFAULTS,
        ]);
    }

    public function update(UpdateAppearanceRequest $request): RedirectResponse
    {
        Gate::authorize('manage-appearance');

        Setting::set('theme.primary_color', $request->validated('primary_color'));
        Setting::set('theme.secondary_color', $request->validated('secondary_color'));

        return back()->with('status', 'Paleta de cores atualizada com sucesso.');
    }
}
