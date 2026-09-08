@php($editing = isset($school))

<div class="form-grid">
    <x-ui.select label="Organização" name="organization_id" required :disabled="$editing">
        @foreach ($organizations as $organization)
            <option value="{{ $organization->id }}" @selected(old('organization_id', $selectedOrganizationId) == $organization->id)>{{ $organization->name }} · {{ $organization->mode === 'network' ? 'Rede' : 'Escola única' }}</option>
        @endforeach
    </x-ui.select>
    @if ($editing)<input type="hidden" name="organization_id" value="{{ $selectedOrganizationId }}">@endif

    <x-ui.input label="Nome da escola" name="name" :value="$school->name ?? ''" required />
    <x-ui.input label="Código da unidade" name="code" :value="$school->code ?? ''" required hint="Exemplo: CENTRO ou ESC-001." />
    <label class="checkbox-field">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $school->is_active ?? true))>
        <span><strong>Escola ativa</strong><small>Desativar preserva usuários e histórico.</small></span>
    </label>
</div>

<div class="form-actions">
    <x-ui.button variant="primary" type="submit">{{ $editing ? 'Salvar alterações' : 'Criar escola' }}</x-ui.button>
    <x-ui.button :href="route('schools.index')">Cancelar</x-ui.button>
</div>
