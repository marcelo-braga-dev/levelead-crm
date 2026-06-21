<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeadDistributionRule;
use App\Models\LeadScoringRule;
use App\Models\Product;
use App\Models\State;
use App\Models\Team;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RuleController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', LeadDistributionRule::class);
        Gate::authorize('viewAny', LeadScoringRule::class);

        return Inertia::render('Admin/Rules/Index', [
            'distributionRules' => LeadDistributionRule::query()
                ->withCount('assignments')
                ->with(['team:id,name', 'state:id,uf', 'product:id,name'])
                ->orderByDesc('priority')
                ->get(),
            'scoringRules' => LeadScoringRule::query()->orderBy('criterion')->get(),
            'teams' => Team::query()->select('id', 'name')->orderBy('name')->get(),
            'states' => State::query()->select('id', 'uf')->orderBy('uf')->get(),
            'products' => Product::query()->select('id', 'name')->orderBy('name')->get(),
        ]);
    }
}
