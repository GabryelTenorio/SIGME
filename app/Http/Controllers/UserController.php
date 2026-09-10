<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Support\AccessCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);
        $actor = $request->user();

        $users = User::query()
            ->where('is_platform_admin', false)
            ->with(['organization', 'schools', 'roles', 'roleAssignments'])
            ->when(
                $actor->is_platform_admin,
                fn (Builder $query) => $query->when(
                    $request->integer('organization_id'),
                    fn (Builder $filtered) => $filtered->where('organization_id', $request->integer('organization_id')),
                ),
                function (Builder $query) use ($actor): void {
                    $query->where('organization_id', $actor->organization_id);
                    if (! $actor->hasPermission('usuarios.gerenciar')) {
                        $query->whereHas('schools', fn (Builder $schools) => $schools
                            ->whereIn('schools.id', $actor->schoolsWithPermission('usuarios.gerenciar')->pluck('id')));
                    }
                },
            )
            ->when($request->string('search')->isNotEmpty(), function (Builder $query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';
                $query->where(fn (Builder $term) => $term->where('name', 'like', $search)->orWhere('email', 'like', $search));
            })
            ->orderBy('name')
            ->get()
            ->filter(fn (User $managedUser): bool => $actor->can('view', $managedUser));

        return view('users.index', [
            'users' => $users,
            'organizations' => $actor->is_platform_admin
                ? Organization::query()->orderBy('name')->get()
                : collect([$actor->organization]),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', User::class);

        return view('users.create', $this->formContext($request));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $data = Arr::except($request->validated(), ['school_ids', 'role_ids']);
            $user = User::query()->create($data);
            $this->syncAssignments($user, $request->validated('school_ids', []), $request->validated('role_ids', []));
        });

        return redirect()->route('users.index')->with('success', 'Usuário criado com seus perfis e escolas.');
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', $this->formContext($request, $user));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user) && ! $request->boolean('is_active')) {
            throw ValidationException::withMessages(['is_active' => 'Você não pode desativar a própria conta.']);
        }

        DB::transaction(function () use ($request, $user): void {
            $data = Arr::except($request->validated(), ['school_ids', 'role_ids']);
            if (blank($data['password'] ?? null)) {
                unset($data['password']);
            }
            $user->update($data);
            $this->syncAssignments($user, $request->validated('school_ids', []), $request->validated('role_ids', []));
        });

        return redirect()->route('users.index')->with('success', 'Usuário atualizado sem perder o histórico.');
    }

    /** @return array<string, mixed> */
    private function formContext(Request $request, ?User $managedUser = null): array
    {
        $actor = $request->user();
        $organizations = $actor->is_platform_admin
            ? Organization::query()->where(fn (Builder $query) => $query
                ->where('is_active', true)
                ->when($managedUser, fn (Builder $query) => $query->orWhere('id', $managedUser->organization_id)))
                ->orderBy('name')->get()
            : collect([$actor->organization]);
        $organizationId = $managedUser?->organization_id ?: ($actor->is_platform_admin
            ? ($request->integer('organization_id') ?: $organizations->first()?->id)
            : $actor->organization_id);
        abort_if($organizationId !== null && ! $organizations->pluck('id')->contains($organizationId), 403);

        $schools = School::query()
            ->where('organization_id', $organizationId)
            ->when(! $actor->is_platform_admin && ! $actor->hasPermission('usuarios.gerenciar'), fn (Builder $query) => $query
                ->whereIn('id', $actor->schoolsWithPermission('usuarios.gerenciar')->pluck('id')))
            ->orderBy('name')
            ->get();

        return [
            'managedUser' => $managedUser,
            'organizations' => $organizations,
            'selectedOrganizationId' => $organizationId,
            'schools' => $schools,
            'roles' => Role::query()
                ->where('organization_id', $organizationId)
                ->where('is_system', true)
                ->whereIn('slug', array_keys(AccessCatalog::roles()))
                ->when(! $actor->is_platform_admin && ! $actor->hasPermission('usuarios.gerenciar'),
                    fn (Builder $query) => $query->where('scope', 'school'))
                ->orderBy('name')
                ->get(),
            'selectedSchoolIds' => $managedUser?->schools()->pluck('schools.id')->all() ?? [],
            'selectedRoleIds' => $managedUser?->roles()->pluck('roles.id')->unique()->all() ?? [],
        ];
    }

    /** @param list<int|string> $schoolIds @param list<int|string> $roleIds */
    private function syncAssignments(User $user, array $schoolIds, array $roleIds): void
    {
        $schoolIds = School::query()->where('organization_id', $user->organization_id)->whereIn('id', $schoolIds)->pluck('id');
        $roles = Role::query()
            ->where('organization_id', $user->organization_id)
            ->where('is_system', true)
            ->whereIn('slug', array_keys(AccessCatalog::roles()))
            ->whereIn('id', $roleIds)
            ->get();

        $user->schools()->sync($schoolIds);
        $user->roleAssignments()->delete();

        foreach ($roles as $role) {
            if ($role->scope === 'organization') {
                RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $role->id, 'school_id' => null]);

                continue;
            }

            foreach ($schoolIds as $schoolId) {
                RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $role->id, 'school_id' => $schoolId]);
            }
        }
    }
}
