<x-layouts.app title="Disponibilidade da categoria" active="categories">
    <header class="page-header"><div><h1>Disponibilidade por escola</h1><p>{{ $category->name }} · {{ $category->organization->name }}</p></div></header>
    <form method="POST" action="{{ route('categories.availability.update', $category) }}" class="form-card">@csrf @method('PUT')
        <div class="section-title"><div><h2>Escolas autorizadas</h2><p>Alterar a disponibilidade não modifica ocorrências antigas.</p></div></div>
        <div class="checkbox-grid">@foreach ($schools as $school)<label class="checkbox-field checkbox-field--compact"><input type="checkbox" name="school_ids[]" value="{{ $school->id }}" @checked(in_array($school->id, old('school_ids', $category->schools->pluck('id')->all())))><span><strong>{{ $school->name }}</strong><small>{{ $school->code }}</small></span></label>@endforeach</div>
        @error('school_ids')<em>{{ $message }}</em>@enderror
        <div class="form-actions"><x-ui.button variant="primary" type="submit">Salvar disponibilidade</x-ui.button><x-ui.button :href="route('categories.index', ['organization_id' => $category->organization_id])">Cancelar</x-ui.button></div>
    </form>
</x-layouts.app>
