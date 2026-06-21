<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeamRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Team::class);

        return Inertia::render('Admin/Teams/Index', [
            'teams' => Team::query()->withCount('users')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        Gate::authorize('create', Team::class);

        Team::create($request->validated());

        return back()->with('status', 'Equipe criada com sucesso.');
    }

    public function update(StoreTeamRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $team->update($request->validated());

        return back()->with('status', 'Equipe atualizada com sucesso.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        Gate::authorize('delete', $team);

        $team->delete();

        return back()->with('status', 'Equipe removida com sucesso.');
    }
}
