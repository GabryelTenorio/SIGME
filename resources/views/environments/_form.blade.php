@php($editing = isset($environment))

<div class="form-grid">
    @if (! $editing && $schools->count() > 1)
        <x-ui.select label="Escola" name="school_id" required data-location-base="{{ route('environments.create') }}" data-location-param="school_id">
            @foreach ($schools as $school)<option value="{{ $school->id }}" @selected(old('school_id', $selectedSchoolId) == $school->id)>{{ $school->name }}</option>@endforeach
        </x-ui.select>
    @else
        <div class="form-field readonly-field"><span>Escola</span><strong>{{ $schools->first()->name }}</strong><small>{{ $schools->first()->organization->name }}</small></div>
        <input type="hidden" name="school_id" value="{{ $selectedSchoolId }}">
    @endif

    <x-ui.input label="Código" name="code" :value="$environment->code ?? ''" required hint="Será normalizado, por exemplo: LAB-01." />
    <x-ui.input label="Nome" name="name" :value="$environment->name ?? ''" required />
    <x-ui.select label="Tipo" name="type" required>
        @foreach (\App\Models\Environment::TYPES as $value => $label)<option value="{{ $value }}" @selected(old('type', $environment->type ?? '') === $value)>{{ $label }}</option>@endforeach
    </x-ui.select>
    <x-ui.select label="Ambiente superior (opcional)" name="parent_id" hint="Use para bloco, andar ou outro agrupamento.">
        <option value="">Sem ambiente superior</option>
        @foreach ($parents as $parent)<option value="{{ $parent->id }}" @selected(old('parent_id', $environment->parent_id ?? '') == $parent->id)>{{ $parent->code }} · {{ $parent->name }}</option>@endforeach
    </x-ui.select>
    <x-ui.input label="Prédio / Bloco" name="building" :value="$environment->building ?? ''" />
    <x-ui.input label="Andar" name="floor" :value="$environment->floor ?? ''" />
    <x-ui.input label="Capacidade" name="capacity" type="number" min="0" :value="$environment->capacity ?? ''" />
    <label class="form-field form-field--wide"><span>Descrição</span><textarea name="description" rows="4">{{ old('description', $environment->description ?? '') }}</textarea>@error('description')<em>{{ $message }}</em>@enderror</label>
    <label class="checkbox-field">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $environment->is_active ?? true))>
        <span><strong>Ambiente ativo</strong><small>Ambientes inativos ficam fora de novas ocorrências, mas mantêm o histórico.</small></span>
    </label>
</div>

<div class="form-actions">
    <x-ui.button variant="primary" type="submit">{{ $editing ? 'Salvar alterações' : 'Criar ambiente' }}</x-ui.button>
    <x-ui.button :href="route('environments.index', ['school_id' => $selectedSchoolId])">Cancelar</x-ui.button>
</div>
