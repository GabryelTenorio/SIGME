@php($editing = isset($category))
<div class="form-grid">
    @if (! $editing && auth()->user()->is_platform_admin && $organizations->count() > 1)
        <x-ui.select label="Organização" name="organization_id" required data-location-base="{{ route('categories.create') }}" data-location-param="organization_id">@foreach ($organizations as $item)<option value="{{ $item->id }}" @selected($selectedOrganizationId === $item->id)>{{ $item->name }}</option>@endforeach</x-ui.select>
    @else
        <div class="form-field readonly-field"><span>Organização</span><strong>{{ $editing ? $category->organization->name : $organization->name }}</strong></div>
        <input type="hidden" name="organization_id" value="{{ $selectedOrganizationId }}">
    @endif
    <x-ui.input label="Nome" name="name" :value="$category->name ?? ''" required hint="O identificador será gerado automaticamente." />
    <x-ui.input label="Ordem de exibição" name="display_order" type="number" min="0" :value="$category->display_order ?? 0" required />
    <label class="form-field form-field--wide"><span>Descrição</span><textarea name="description" rows="4">{{ old('description', $category->description ?? '') }}</textarea>@error('description')<em>{{ $message }}</em>@enderror</label>
    <label class="checkbox-field"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true)) @disabled(($category->is_fallback ?? false))><span><strong>Categoria ativa</strong><small>Categorias inativas não aparecem em novas ocorrências.</small></span></label>
    @if (($category->is_fallback ?? false))<input type="hidden" name="is_active" value="1">@endif

    @if (! $editing)
        <div class="form-field form-field--wide"><span>Escolas onde estará disponível</span>
            @if ($organization->mode === 'single_school')
                <div class="availability-note"><x-ui.icon name="check" /><span>Disponibilidade automática na escola única.</span></div>
                @foreach ($schools as $school)<input type="hidden" name="school_ids[]" value="{{ $school->id }}">@endforeach
            @else
                <div class="checkbox-grid">@foreach ($schools as $school)<label class="checkbox-field checkbox-field--compact"><input type="checkbox" name="school_ids[]" value="{{ $school->id }}" @checked(in_array($school->id, old('school_ids', $schools->pluck('id')->all())))><span><strong>{{ $school->name }}</strong><small>{{ $school->code }}</small></span></label>@endforeach</div>
            @endif
            @error('school_ids')<em>{{ $message }}</em>@enderror
        </div>
    @endif
</div>
<div class="form-actions"><x-ui.button variant="primary" type="submit">{{ $editing ? 'Salvar alterações' : 'Criar categoria' }}</x-ui.button><x-ui.button :href="route('categories.index', ['organization_id' => $selectedOrganizationId])">Cancelar</x-ui.button></div>
