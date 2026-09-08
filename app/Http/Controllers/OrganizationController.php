<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizationRequest;
use App\Models\Organization;
use App\Support\AccessCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Organization::class);

        return view('organizations.index', [
            'organizations' => Organization::query()
                ->withCount(['schools', 'users'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Organization::class);

        return view('organizations.create');
    }

    public function store(OrganizationRequest $request, AccessCatalog $catalog): RedirectResponse
    {
        $organization = Organization::query()->create($request->validated());
        $catalog->provision($organization);

        return redirect()->route('organizations.index')->with('success', 'Organização criada com os perfis padrão do SIGME.');
    }

    public function edit(Organization $organization): View
    {
        $this->authorize('update', $organization);

        return view('organizations.edit', compact('organization'));
    }

    public function update(OrganizationRequest $request, Organization $organization): RedirectResponse
    {
        if ($request->string('mode')->toString() === 'single_school' && $organization->schools()->count() > 1) {
            throw ValidationException::withMessages([
                'mode' => 'Uma organização com várias escolas não pode ser alterada para escola única.',
            ]);
        }

        $organization->update($request->validated());

        return redirect()->route('organizations.index')->with('success', 'Organização atualizada.');
    }
}
