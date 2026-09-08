@php($editing = isset($organization))

<div class="form-grid">
    <x-ui.input label="Nome da organização" name="name" :value="$organization->name ?? ''" required />
    <x-ui.input label="Identificador" name="slug" :value="$organization->slug ?? ''" hint="Gerado automaticamente quando deixado vazio." />

    <x-ui.select label="Modelo de operação" name="mode" required>
        <option value="single_school" @selected(old('mode', $organization->mode ?? 'single_school') === 'single_school')>Escola independente</option>
        <option value="network" @selected(old('mode', $organization->mode ?? '') === 'network')>Rede de escolas</option>
    </x-ui.select>
    <x-ui.input label="Limite para aprovação de compras (R$)" name="approval_threshold" type="number" step="0.01" min="0" :value="$organization->approval_threshold ?? ''" hint="Deixe vazio quando não houver um limite definido." />

    <label class="checkbox-field">
        <input type="checkbox" name="allows_student_representative" value="1" @checked(old('allows_student_representative', $organization->allows_student_representative ?? false))>
        <span><strong>Representante de aluno permitido</strong><small>Habilita o perfil limitado para comunicar problemas.</small></span>
    </label>
    <label class="checkbox-field">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $organization->is_active ?? true))>
        <span><strong>Organização ativa</strong><small>Desativar bloqueia novas operações sem apagar o histórico.</small></span>
    </label>
</div>

<div class="form-actions">
    <x-ui.button variant="primary" type="submit">{{ $editing ? 'Salvar alterações' : 'Criar organização' }}</x-ui.button>
    <x-ui.button :href="route('organizations.index')">Cancelar</x-ui.button>
</div>
