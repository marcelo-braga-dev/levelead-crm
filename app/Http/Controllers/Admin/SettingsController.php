<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tela "Configurações" (admin apenas, Gate `manage-settings`) — fecha a lacuna de parâmetros de
 * negócio que só eram editáveis via tinker (Hardening pós-MVP H2 + limiares da integração com
 * Google Places) e permite cadastrar a chave da Google Places API sem acesso SSH ao `.env` do
 * VPS. A chave nunca volta em texto puro para o frontend (ver `edit()`) — só um indicador
 * mascarado, mesmo princípio do campo "Nova senha" em Admin/Users.
 */
class SettingsController extends Controller
{
    private const DEFAULTS = [
        'compliance_max_calls_per_day' => 2,
        'compliance_max_calls_per_month' => 15,
        'sla_no_interaction_hours' => 24,
        'sla_negotiation_days' => 15,
        'sla_no_return_days' => 7,
        'sla_attempts_exhausted_threshold' => 7,
        'leads_archive_stale_months' => 12,
        'scoring_intent_decay_grace_days' => 7,
        'scoring_intent_decay_rate_per_day' => 2,
        'google_places_monthly_budget_cap' => 500,
        'google_places_low_rating_threshold' => 3.5,
        'google_places_low_review_threshold' => 10,
    ];

    private const KEY_MAP = [
        'compliance_max_calls_per_day' => 'compliance.max_calls_per_day',
        'compliance_max_calls_per_month' => 'compliance.max_calls_per_month',
        'sla_no_interaction_hours' => 'sla.no_interaction_hours',
        'sla_negotiation_days' => 'sla.negotiation_days',
        'sla_no_return_days' => 'sla.no_return_days',
        'sla_attempts_exhausted_threshold' => 'sla.attempts_exhausted_threshold',
        'leads_archive_stale_months' => 'leads.archive_stale_months',
        'scoring_intent_decay_grace_days' => 'scoring.intent_decay_grace_days',
        'scoring_intent_decay_rate_per_day' => 'scoring.intent_decay_rate_per_day',
        'google_places_monthly_budget_cap' => 'google_places.monthly_budget_cap',
        'google_places_low_rating_threshold' => 'google_places.low_rating_threshold',
        'google_places_low_review_threshold' => 'google_places.low_review_threshold',
    ];

    public function edit(): Response
    {
        Gate::authorize('manage-settings');

        $values = [];
        foreach (self::KEY_MAP as $field => $settingKey) {
            $values[$field] = Setting::get($settingKey, self::DEFAULTS[$field]);
        }

        $apiKey = Setting::get('integrations.google_places_api_key');

        return Inertia::render('Admin/Settings/Index', [
            'values' => $values,
            'defaults' => self::DEFAULTS,
            'googlePlacesApiKey' => [
                'configured' => filled($apiKey),
                'masked' => filled($apiKey) ? '••••'.substr((string) $apiKey, -4) : null,
            ],
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        Gate::authorize('manage-settings');

        foreach (self::KEY_MAP as $field => $settingKey) {
            Setting::set($settingKey, $request->validated($field));
        }

        if (filled($request->validated('google_places_api_key'))) {
            Setting::set('integrations.google_places_api_key', $request->validated('google_places_api_key'));
        }

        return back()->with('status', 'Configurações atualizadas com sucesso.');
    }
}
