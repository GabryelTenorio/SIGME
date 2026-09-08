@php
    $editing = (bool) $managedUser;
    $checkedSchools = collect(old('school_ids', $selectedSchoolIds))->map(fn ($id) => (int) $id);
    $checkedRoles = collect(old('role_ids', $selectedRoleIds))->map(fn ($id) => (int) $id);
@endphp

<div class="form-grid">
    @if (auth()->user()->is_platform_admin && ! $editing)
        <x-ui.select label="Organização" name="organization_id" required onchange="window.location='{{ route('users.create') }}?organization_id='+this.value">
            @foreach ($organizations as $organization)<option value="{{ $organization->id }}" @selected($selectedOrganizationId == $organization->id)>{{ $organization->name }}</option>@endforeach
        </x-ui.select>
    @else
        <x-ui.select label="Organização" name="organization_id" required disabled>
            @foreach ($organizations as $organization)<option value="{{ $organization->id }}" @selected($selectedOrganizationId == $organization->id)>{{ $organization->name }}</option>@endforeach
        </x-ui.select>
        <input type="hidden" name="organization_id" value="{{ $selectedOrganizationId }}">
    @endif

    <x-ui.input label="Nome completo" name="name" :value="$managedUser?->name ?? ''" required />
    <x-ui.input label="E-mail" name="email" type="email" :value="$managedUser?->email ?? ''" required autocomplete="off" />
    <x-ui.input
        label="Senha"
        name="password"
        type="password"
        :required="! $editing"
        autocomplete="new-password"
        :hint="$editing ? 'Deixe vazio para manter a senha atual.' : 'Mínimo de 12 caracteres.'"
    />

    <div class="form-field form-field--full">
        <span>Perfis <b aria-hidden="true">*</b></span>
        <div class="choice-grid">
            @forelse ($roles as $role)
                <label class="checkbox-field">
                    <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked($checkedRoles->contains($role->id))>
                    <span><strong>{{ $role->name }}</strong><small>{{ $role->scope === 'organization' ? 'Válido para toda a organização' : 'Aplicado às escolas selecionadas' }}</small></span>
                </label>
            @empty
                <div class="alert alert--danger">Esta organização ainda não possui perfis provisionados.</div>
            @endforelse
        </div>
        @error('role_ids')<em>{{ $message }}</em>@enderror
    </div>

    <div class="form-field form-field--full">
        <span>Escolas</span>
        <div class="choice-grid">
            @forelse ($schools as $school)
                <label class="checkbox-field">
                    <input type="checkbox" name="school_ids[]" value="{{ $school->id }}" @checked($checkedSchools->contains($school->id))>
                    <span><strong>{{ $school->name }}</strong><small>{{ $school->code }}</small></span>
                </label>
            @empty
                <div class="alert alert--danger">Cadastre uma escola antes de atribuir perfis escolares.</div>
            @endforelse
        </div>
        @error('school_ids')<em>{{ $message }}</em>@enderror
    </div>

    <label class="checkbox-field form-field--full">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $managedUser?->is_active ?? true))>
        <span><strong>Conta ativa</strong><small>Desativar impede o login, mas preserva vínculos e histórico.</small></span>
    </label>
</div>

<div class="form-actions">
    <x-ui.button variant="primary" type="submit">{{ $editing ? 'Salvar alterações' : 'Criar usuário' }}</x-ui.button>
    <x-ui.button :href="route('users.index')">Cancelar</x-ui.button>
</div>
