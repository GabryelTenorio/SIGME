<x-layouts.app title="Nova ocorrência" active="occurrences">
    <header class="page-header"><div><h1>Registrar ocorrência</h1><p>Informe o problema observado. A prioridade será sugerida pelo SIGME e confirmada na triagem.</p></div></header>
    <form method="POST" enctype="multipart/form-data" action="{{ route('occurrences.store') }}" class="form-card">@csrf
        <div class="form-grid">
            @if ($schools->count() > 1)
                <x-ui.select label="Escola" name="school_id" required onchange="window.location='{{ route('occurrences.create') }}?school_id='+this.value">@foreach ($schools as $school)<option value="{{ $school->id }}" @selected(old('school_id', $selectedSchoolId) == $school->id)>{{ $school->name }}</option>@endforeach</x-ui.select>
            @else <div class="form-field readonly-field"><span>Escola</span><strong>{{ $schools->first()->name }}</strong></div><input type="hidden" name="school_id" value="{{ $selectedSchoolId }}"> @endif
            <x-ui.select label="Ambiente" name="environment_id" required><option value="">Selecione</option>@foreach ($environments as $environment)<option value="{{ $environment->id }}" @selected(old('environment_id') == $environment->id)>{{ $environment->code }} · {{ $environment->name }}</option>@endforeach</x-ui.select>
            <x-ui.select label="Categoria" name="occurrence_category_id" required><option value="">Selecione</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected(old('occurrence_category_id') == $category->id)>{{ $category->name }}</option>@endforeach</x-ui.select>
            <x-ui.input label="Título" name="title" required hint="Resuma o problema em uma frase." />
            <label class="form-field form-field--wide"><span>Descrição <b>*</b></span><textarea name="description" rows="5" required>{{ old('description') }}</textarea>@error('description')<em>{{ $message }}</em>@enderror</label>
            <x-ui.select label="Impacto" name="impact" required><option value="LOW">Baixo · atividade continua normalmente</option><option value="MEDIUM" @selected(old('impact') === 'MEDIUM')>Médio · afeta parcialmente o uso</option><option value="HIGH" @selected(old('impact') === 'HIGH')>Alto · impede ou prejudica significativamente</option></x-ui.select>
            <x-ui.select label="Urgência percebida" name="perceived_urgency" required><option value="NORMAL">Normal · pode aguardar atendimento</option><option value="SOON" @selected(old('perceived_urgency') === 'SOON')>Breve · atendimento em curto prazo</option><option value="IMMEDIATE" @selected(old('perceived_urgency') === 'IMMEDIATE')>Imediata · risco ou impossibilidade grave</option></x-ui.select>
            <x-ui.input label="Evidências opcionais" name="attachments[]" type="file" accept="image/jpeg,image/png,application/pdf" multiple hint="Até 5 imagens JPEG/PNG ou PDF, com no máximo 10 MB por arquivo." />
            @error('attachments')<em>{{ $message }}</em>@enderror
            @error('attachments.*')<em>{{ $message }}</em>@enderror
        </div>
        <div class="form-actions"><x-ui.button variant="primary" type="submit">Registrar ocorrência</x-ui.button><x-ui.button :href="route('occurrences.index')">Cancelar</x-ui.button></div>
    </form>
</x-layouts.app>
