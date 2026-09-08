<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnvironmentRequest;
use App\Models\Environment;
use App\Models\EnvironmentHistory;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EnvironmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Environment::class);
        $schools = $this->eligibleSchools($request->user(), 'ambientes.visualizar');
        $schoolIds = $schools->pluck('id');
        $selectedSchoolId = $schoolIds->contains($request->integer('school_id')) ? $request->integer('school_id') : null;

        $environments = Environment::query()
            ->with(['school.organization', 'parent'])
            ->withCount('children')
            ->whereIn('school_id', $schoolIds)
            ->when($selectedSchoolId, fn (Builder $query) => $query->where('school_id', $selectedSchoolId))
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->string('type')))
            ->when($request->input('status') === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->where(fn (Builder $filtered) => $filtered
                    ->where('name', 'like', $search)->orWhere('code', 'like', $search));
            })
            ->orderBy('school_id')->orderBy('name')->get();

        return view('environments.index', compact('environments', 'schools', 'selectedSchoolId'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Environment::class);
        $schools = $this->eligibleSchools($request->user(), 'ambientes.criar');
        abort_if($schools->isEmpty(), 403);
        $selectedSchoolId = $schools->pluck('id')->contains($request->integer('school_id'))
            ? $request->integer('school_id') : $schools->first()->id;

        return view('environments.create', [
            'schools' => $schools,
            'selectedSchoolId' => $selectedSchoolId,
            'parents' => Environment::query()->where('school_id', $selectedSchoolId)->orderBy('name')->get(),
        ]);
    }

    public function store(EnvironmentRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $environment = Environment::query()->create($request->validated());
            $this->recordHistory($environment, $request->user(), 'created', ['after' => $environment->only($environment->getFillable())]);
        });

        return redirect()->route('environments.index', ['school_id' => $request->integer('school_id')])
            ->with('success', 'Ambiente criado e vinculado à escola.');
    }

    public function edit(Request $request, Environment $environment): View
    {
        $this->authorize('update', $environment);
        $parents = Environment::query()->where('school_id', $environment->school_id)
            ->whereKeyNot($environment->id)->orderBy('name')->get();

        return view('environments.edit', [
            'environment' => $environment->load(['school.organization', 'histories.user']),
            'schools' => collect([$environment->school]),
            'selectedSchoolId' => $environment->school_id,
            'parents' => $parents,
        ]);
    }

    public function update(EnvironmentRequest $request, Environment $environment): RedirectResponse
    {
        DB::transaction(function () use ($request, $environment): void {
            $before = $environment->only($environment->getFillable());
            $environment->update($request->validated());
            $after = $environment->fresh()->only($environment->getFillable());
            $action = $before['is_active'] && ! $after['is_active'] ? 'deactivated'
                : (! $before['is_active'] && $after['is_active'] ? 'reactivated' : 'updated');
            $this->recordHistory($environment, $request->user(), $action, ['before' => $before, 'after' => $after]);
        });

        return redirect()->route('environments.index', ['school_id' => $environment->school_id])
            ->with('success', 'Ambiente atualizado.');
    }

    public function deactivate(Request $request, Environment $environment): RedirectResponse
    {
        $this->authorize('deactivate', $environment);
        if ($environment->is_active) {
            $environment->update(['is_active' => false]);
            $this->recordHistory($environment, $request->user(), 'deactivated', ['is_active' => ['before' => true, 'after' => false]]);
        }

        return back()->with('success', 'Ambiente desativado sem remover seu histórico.');
    }

    private function eligibleSchools(User $user, string $permission): Collection
    {
        return $user->accessibleSchools()->filter(
            fn (School $school) => $user->hasPermission($permission, $school)
                || $user->hasPermission('ambientes.gerenciar', $school),
        )->values();
    }

    private function recordHistory(Environment $environment, User $user, string $action, array $changes): void
    {
        EnvironmentHistory::query()->create([
            'environment_id' => $environment->id,
            'user_id' => $user->id,
            'action' => $action,
            'changes' => $changes,
        ]);
    }
}
