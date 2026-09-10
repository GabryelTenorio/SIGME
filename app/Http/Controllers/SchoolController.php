<?php

namespace App\Http\Controllers;

use App\Http\Requests\SchoolRequest;
use App\Models\Organization;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SchoolController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', School::class);
        $user = $request->user();

        $schools = School::query()
            ->with('organization')
            ->withCount('users')
            ->when(
                $user->is_platform_admin,
                fn (Builder $query) => $query->when(
                    $request->integer('organization_id'),
                    fn (Builder $filtered) => $filtered->where('organization_id', $request->integer('organization_id')),
                ),
                function (Builder $query) use ($user): void {
                    $query->where('organization_id', $user->organization_id);
                    if (! $user->hasPermission('escolas.gerenciar')) {
                        $query->whereIn('id', $user->accessibleSchools()->pluck('id'));
                    }
                },
            )
            ->orderBy('name')
            ->get();

        return view('schools.index', [
            'schools' => $schools,
            'organizations' => $user->is_platform_admin
                ? Organization::query()->orderBy('name')->get()
                : collect([$user->organization]),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', School::class);

        return view('schools.create', [
            'organizations' => $request->user()->is_platform_admin
                ? Organization::query()->where('is_active', true)->orderBy('name')->get()
                : collect([$request->user()->organization]),
            'selectedOrganizationId' => $request->integer('organization_id') ?: $request->user()->organization_id,
        ]);
    }

    public function store(SchoolRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $organization = Organization::query()->lockForUpdate()->findOrFail($request->integer('organization_id'));
            $this->ensureOrganizationAcceptsSchool($organization);
            School::query()->create($request->validated());
        });

        return redirect()->route('schools.index')->with('success', 'Escola criada e vinculada à organização.');
    }

    public function edit(School $school): View
    {
        $this->authorize('update', $school);

        return view('schools.edit', [
            'school' => $school,
            'organizations' => collect([$school->organization]),
            'selectedOrganizationId' => $school->organization_id,
        ]);
    }

    public function update(SchoolRequest $request, School $school): RedirectResponse
    {
        $data = $request->validated();
        $data['organization_id'] = $school->organization_id;
        $school->update($data);

        return redirect()->route('schools.index')->with('success', 'Escola atualizada.');
    }

    private function ensureOrganizationAcceptsSchool(Organization $organization): void
    {
        if ($organization->mode === 'single_school' && $organization->schools()->exists()) {
            throw ValidationException::withMessages([
                'organization_id' => 'Esta organização está configurada para possuir apenas uma escola.',
            ]);
        }
    }
}
