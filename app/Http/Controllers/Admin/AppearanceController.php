<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAppearanceRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tela "Aparência" (admin apenas, ver Gate `manage-appearance`) — paleta Primary/Secondary
 * e cores por etapa do Kanban, compartilhadas com toda a aplicação via `theme` em
 * HandleInertiaRequests::share() (esta classe é a fonte da verdade dos defaults; o
 * middleware duplica o literal como fallback, mesmo precedente do par primary/secondary).
 */
class AppearanceController extends Controller
{
    private const DEFAULTS = [
        'primary_color' => '#4F46E5',
        'secondary_color' => '#06B6D4',
    ];

    public const DEFAULT_STAGE_COLORS = [
        'new' => '#3B82F6',
        'attempting_contact' => '#F59E0B',
        'contact_made' => '#8B5CF6',
        'qualified' => '#0EA5E9',
        'proposal_sent' => '#EC4899',
        'negotiation' => '#F97316',
        'won' => '#10B981',
        'lost' => '#EF4444',
    ];

    public function edit(): Response
    {
        Gate::authorize('manage-appearance');

        return Inertia::render('Admin/Appearance/Index', [
            'current' => [
                'primary_color' => Setting::get('theme.primary_color', self::DEFAULTS['primary_color']),
                'secondary_color' => Setting::get('theme.secondary_color', self::DEFAULTS['secondary_color']),
                'stage_colors' => Setting::get('kanban.stage_colors', self::DEFAULT_STAGE_COLORS),
            ],
            'defaults' => [
                ...self::DEFAULTS,
                'stage_colors' => self::DEFAULT_STAGE_COLORS,
            ],
        ]);
    }

    public function update(UpdateAppearanceRequest $request): RedirectResponse
    {
        Gate::authorize('manage-appearance');

        Setting::set('theme.primary_color', $request->validated('primary_color'));
        Setting::set('theme.secondary_color', $request->validated('secondary_color'));

        $stageValues = array_column(LeadStage::cases(), 'value');
        $stageColors = Arr::only($request->validated('stage_colors'), $stageValues);
        Setting::set('kanban.stage_colors', [...self::DEFAULT_STAGE_COLORS, ...$stageColors]);

        return back()->with('status', 'Paleta de cores atualizada com sucesso.');
    }
}
