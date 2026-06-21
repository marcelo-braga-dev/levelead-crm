<?php

namespace App\Http\Controllers\Admin;

use App\DomainServices\AuditLogReconstructor;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Proposal;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tela de auditoria (admin/manager apenas, ver AuditLogPolicy). Index é o feed global de
 * audit_logs (qualquer entidade); Show é a linha do tempo completa de uma entidade
 * específica + reconstrução de estado num ponto no tempo via AuditLogReconstructor.
 */
class AuditController extends Controller
{
    /** @var array<string, class-string> slug de URL => FQCN, para não aceitar classe arbitrária na rota */
    private const ENTITY_SLUGS = [
        'lead' => Lead::class,
        'company' => Company::class,
        'proposal' => Proposal::class,
        'follow_up' => FollowUp::class,
    ];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AuditLog::class);

        $query = AuditLog::query()->latest('created_at');

        if ($entitySlug = $request->query('auditable_type')) {
            $query->where('auditable_type', self::ENTITY_SLUGS[$entitySlug] ?? '__none__');
        }

        if ($actorType = $request->query('actor_type')) {
            $query->where('actor_type', $actorType);
        }

        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        $logs = $query->paginate(25)->withQueryString();

        $logs->getCollection()->transform(fn (AuditLog $log) => [
            'id' => $log->id,
            'auditable_slug' => array_search($log->auditable_type, self::ENTITY_SLUGS, true) ?: null,
            'auditable_id' => $log->auditable_id,
            'action' => $log->action->value,
            'actor_type' => $log->actor_type->value,
            'actor_label' => $log->actor_label,
            'created_at' => $log->created_at,
        ]);

        return Inertia::render('Admin/Audit/Index', [
            'logs' => $logs,
            'filters' => $request->only(['auditable_type', 'actor_type', 'from', 'to']),
            'entityTypes' => array_keys(self::ENTITY_SLUGS),
        ]);
    }

    public function show(Request $request, string $entityType, int $entityId): Response
    {
        Gate::authorize('viewAny', AuditLog::class);

        $modelClass = self::ENTITY_SLUGS[$entityType] ?? abort(404);

        $query = $modelClass::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        $entity = $query->findOrFail($entityId);

        $logs = AuditLog::query()
            ->where('auditable_type', $modelClass)
            ->where('auditable_id', $entityId)
            ->oldest('created_at')
            ->get(['id', 'action', 'old_values', 'new_values', 'actor_type', 'actor_label', 'created_at']);

        $reconstructedState = null;
        $at = $request->query('at');

        if ($at) {
            $reconstructedState = app(AuditLogReconstructor::class)->stateAt($entity, Carbon::parse($at));
        }

        return Inertia::render('Admin/Audit/Show', [
            'entityType' => $entityType,
            'entityId' => $entityId,
            'currentState' => $entity->toArray(),
            'logs' => $logs,
            'reconstructedState' => $reconstructedState,
            'reconstructedAt' => $at,
        ]);
    }
}
