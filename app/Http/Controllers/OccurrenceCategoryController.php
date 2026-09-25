<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryAvailabilityRequest;
use App\Http\Requests\OccurrenceCategoryRequest;
use App\Models\OccurrenceCategory;
use App\Models\Organization;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OccurrenceCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', OccurrenceCategory::class);
        $user = $request->user();
        $organizationId = $user->is_platform_admin
            ? ($request->integer('organization_id') ?: null)
            : $user->organization_id;

        $categories = OccurrenceCategory::query()
            ->with(['organization' => fn ($query) => $query->withCount('schools'), 'schools'])
            ->when($organizationId, fn (Builder $query) => $query->where('organization_id', $organizationId))
            ->when($request->input('status') === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->where(fn (Builder $filtered) => $filtered->where('name', 'like', $search)->orWhere('identifier', 'like', $search));
            })
            ->when(! $user->is_platform_admin, fn (Builder $query) => $query->where('organization_id', $user->organization_id))
            ->orderBy('organization_id')->orderBy('display_order')->orderBy('name')->get();

        return view('categories.index', [
            'categories' => $categories,
            'organizations' => $user->is_platform_admin ? Organization::query()->orderBy('name')->get() : collect([$user->organization]),
            'selectedOrganizationId' => $organizationId,
            'canCreateAny' => $user->is_platform_admin || ($user->organization && $this->canCreateIn($request, $user->organization)),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', OccurrenceCategory::class);
        $organizations = $request->user()->is_platform_admin
            ? Organization::query()->where('is_active', true)->orderBy('name')->get()
            : collect([$request->user()->organization]);
        $selectedOrganizationId = $organizations->pluck('id')->contains($request->integer('organization_id'))
            ? $request->integer('organization_id') : $organizations->first()?->id;
        abort_unless($selectedOrganizationId, 403);
        $organization = Organization::query()->findOrFail($selectedOrganizationId);
        abort_unless($this->canCreateIn($request, $organization), 403);

        return view('categories.create', [
            'organizations' => $organizations,
            'selectedOrganizationId' => $selectedOrganizationId,
            'organization' => $organization,
            'schools' => $this->creatableSchools($request, $organization),
        ]);
    }

    public function store(OccurrenceCategoryRequest $request): RedirectResponse
    {
        $category = DB::transaction(function () use ($request): OccurrenceCategory {
            $category = OccurrenceCategory::query()->create(Arr::except($request->validated(), 'school_ids'));
            $schoolIds = $category->organization->mode === 'single_school'
                ? $category->organization->schools()->pluck('id')->all()
                : $request->validated('school_ids', []);
            $category->schools()->sync($schoolIds);

            return $category;
        });

        return redirect()->route('categories.index', ['organization_id' => $category->organization_id])
            ->with('success', 'Categoria criada para a organização.');
    }

    public function edit(OccurrenceCategory $category): View
    {
        $this->authorize('update', $category);

        return view('categories.edit', [
            'category' => $category->load('organization'),
            'organizations' => collect([$category->organization]),
            'selectedOrganizationId' => $category->organization_id,
        ]);
    }

    public function update(OccurrenceCategoryRequest $request, OccurrenceCategory $category): RedirectResponse
    {
        $category->update(Arr::except($request->validated(), ['school_ids', 'organization_id']));

        return redirect()->route('categories.index', ['organization_id' => $category->organization_id])->with('success', 'Categoria atualizada.');
    }

    public function deactivate(Request $request, OccurrenceCategory $category): RedirectResponse
    {
        $this->authorize('deactivate', $category);
        abort_if($category->is_fallback, 422, 'A categoria de fallback deve permanecer ativa.');
        $category->update(['is_active' => false]);

        return back()->with('success', 'Categoria desativada sem remover vínculos ou histórico.');
    }

    public function availability(Request $request, OccurrenceCategory $category): View
    {
        $this->authorize('manageAvailability', $category);

        return view('categories.availability', [
            'category' => $category->load(['organization', 'schools']),
            'schools' => $this->manageableSchools($request, $category),
        ]);
    }

    public function updateAvailability(CategoryAvailabilityRequest $request, OccurrenceCategory $category): RedirectResponse
    {
        $manageableIds = $this->manageableSchools($request, $category)->pluck('id');
        $preservedIds = $category->schools()->whereNotIn('schools.id', $manageableIds)->pluck('schools.id');
        $selectedIds = collect($request->validated('school_ids', []))->intersect($manageableIds);
        $category->schools()->sync($preservedIds->merge($selectedIds)->unique()->all());

        return redirect()->route('categories.index', ['organization_id' => $category->organization_id])
            ->with('success', 'Disponibilidade atualizada para as escolas autorizadas.');
    }

    private function manageableSchools(Request $request, OccurrenceCategory $category): Collection
    {
        $user = $request->user();

        return $category->organization->schools()->where('is_active', true)->orderBy('name')->get()->filter(
            fn (School $school) => $user->canAccessSchool($school)
                && $user->hasPermission('categorias.gerenciar_disponibilidade', $school),
        )->values();
    }

    private function canCreateIn(Request $request, Organization $organization): bool
    {
        return $this->creatableSchools($request, $organization)->isNotEmpty();
    }

    private function creatableSchools(Request $request, Organization $organization): Collection
    {
        $user = $request->user();
        if (! $user->is_platform_admin && $user->organization_id !== $organization->id) {
            return collect();
        }

        $schools = $organization->schools()->where('is_active', true)->orderBy('name')->get();
        if ($user->is_platform_admin || $user->hasPermission('categorias.criar')) {
            return $schools;
        }

        return $schools->filter(
            fn (School $school) => $user->canAccessSchool($school)
                && $user->hasPermission('categorias.criar', $school),
        )->values();
    }
}
