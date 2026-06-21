<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\LeadStage;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Painel gerencial básico: contagens por etapa/consultor/equipe/origem + as 2 fórmulas
 * fechadas no plano (Taxa de Conversão, Ticket Médio), segmentável por período/equipe/
 * consultor/origem, com export CSV dos leads que casam com o filtro atual.
 *
 * Não filtra `archived_at` — o filtro de período já é quem decide o recorte; leads
 * arquivados continuam contando como oportunidades reais que existiram naquele intervalo.
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Lead::class);

        $filters = $this->resolveFilters($request);
        $query = $this->scopedQuery($request, $filters);

        $total = (clone $query)->count();
        $newCount = (clone $query)->where('stage', LeadStage::New->value)->count();
        $wonCount = (clone $query)->where('stage', LeadStage::Won->value)->count();
        $wonValueSum = (float) (clone $query)->where('stage', LeadStage::Won->value)->sum('won_value');

        $conversionRate = ($total - $newCount) > 0 ? $wonCount / ($total - $newCount) : null;
        $averageTicket = $wonCount > 0 ? $wonValueSum / $wonCount : null;

        return Inertia::render('Dashboard/Index', [
            'filters' => $filters,
            'teams' => Team::query()->select('id', 'name')->get(),
            'consultants' => User::query()->where('role', UserRole::Consultant->value)->select('id', 'name')->get(),
            'leadSources' => LeadSource::query()->select('id', 'name')->get(),
            'summary' => [
                'total' => $total,
                'new' => $newCount,
                'won' => $wonCount,
                'conversion_rate' => $conversionRate,
                'average_ticket' => $averageTicket,
            ],
            'byStage' => $this->countBy(clone $query, 'stage', fn (LeadStage $value) => $value->label()),
            'byConsultant' => $this->countByRelated(clone $query, 'assigned_to', User::class, 'Sem consultor'),
            'byTeam' => $this->countByRelated(clone $query, 'team_id', Team::class, 'Sem equipe'),
            'byLeadSource' => $this->countByRelated(clone $query, 'lead_source_id', LeadSource::class, 'Sem origem'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Lead::class);

        $filters = $this->resolveFilters($request);
        $leads = $this->scopedQuery($request, $filters)
            ->with(['company:id,razao_social,cnpj', 'assignedTo:id,name', 'team:id,name', 'leadSource:id,name'])
            ->get();

        $callback = function () use ($leads) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Company', 'CNPJ', 'Etapa', 'Consultor', 'Equipe', 'Origem', 'Valor Ganho', 'Criado em', 'Entrou na etapa em']);

            foreach ($leads as $lead) {
                fputcsv($handle, [
                    $lead->company->razao_social,
                    $lead->company->cnpj,
                    $lead->stage->label(),
                    $lead->assignedTo?->name ?? '',
                    $lead->team?->name ?? '',
                    $lead->leadSource?->name ?? '',
                    $lead->won_value ?? '',
                    $lead->created_at?->format('d/m/Y H:i') ?? '',
                    $lead->stage_entered_at?->format('d/m/Y H:i') ?? '',
                ]);
            }

            fclose($handle);
        };

        $filename = 'leads-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload($callback, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array{from: string, to: string, team_id: ?int, assigned_to: ?int, lead_source_id: ?int} */
    private function resolveFilters(Request $request): array
    {
        return [
            'from' => $request->query('from') ?: now()->subDays(30)->toDateString(),
            'to' => $request->query('to') ?: now()->toDateString(),
            'team_id' => $request->query('team_id') ? (int) $request->query('team_id') : null,
            'assigned_to' => $request->query('assigned_to') ? (int) $request->query('assigned_to') : null,
            'lead_source_id' => $request->query('lead_source_id') ? (int) $request->query('lead_source_id') : null,
        ];
    }

    /** @param array{from: string, to: string, team_id: ?int, assigned_to: ?int, lead_source_id: ?int} $filters */
    private function scopedQuery(Request $request, array $filters)
    {
        $query = Lead::query()
            ->whereBetween('created_at', [
                Carbon::parse($filters['from'])->startOfDay(),
                Carbon::parse($filters['to'])->endOfDay(),
            ]);

        if ($filters['team_id']) {
            $query->where('team_id', $filters['team_id']);
        }

        if ($filters['assigned_to']) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if ($filters['lead_source_id']) {
            $query->where('lead_source_id', $filters['lead_source_id']);
        }

        $user = $request->user();

        if ($user->role === UserRole::Consultant) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id);

                if ($user->team_id !== null) {
                    $q->orWhere('team_id', $user->team_id);
                }
            });
        }

        return $query;
    }

    /** @return array<int, array{label: string, total: int}> */
    private function countBy($query, string $column, callable $labelResolver): array
    {
        return $query
            ->select($column, DB::raw('count(*) as total'))
            ->groupBy($column)
            ->get()
            ->map(fn ($row) => ['label' => $labelResolver($row->{$column}), 'total' => $row->total])
            ->values()
            ->all();
    }

    /** @return array<int, array{label: string, total: int}> */
    private function countByRelated($query, string $column, string $modelClass, string $emptyLabel): array
    {
        $rows = $query
            ->select($column, DB::raw('count(*) as total'))
            ->groupBy($column)
            ->get();

        $ids = $rows->pluck($column)->filter()->values();
        $names = $modelClass::query()->whereIn('id', $ids)->pluck('name', 'id');

        return $rows
            ->map(fn ($row) => [
                'label' => $row->{$column} ? ($names[$row->{$column}] ?? $emptyLabel) : $emptyLabel,
                'total' => $row->total,
            ])
            ->values()
            ->all();
    }
}
