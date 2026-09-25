<x-layouts.app title="Nova Ordem de Serviço" active="service-orders">
    <header class="page-header"><div><span class="eyebrow">{{ $occurrence->protocol }}</span><h1>Nova Ordem de Serviço</h1><p>{{ $occurrence->school->name }} · {{ $occurrence->environment->name }}</p></div></header>
    <form method="POST" action="{{ route('service-orders.store') }}" class="form-card">
        @csrf
        <input type="hidden" name="occurrence_id" value="{{ $occurrence->id }}">
        <div class="origin-summary"><div><span>Ocorrência</span><strong>{{ $occurrence->title }}</strong></div><div><span>Categoria</span><strong>{{ $occurrence->category->name }}</strong></div><div><span>Prioridade</span><strong>{{ \App\Models\Occurrence::PRIORITIES[$occurrence->priority()] }}</strong></div></div>
        <div class="form-grid">
            <x-ui.input label="Título / objetivo do serviço" name="title" :value="old('title', $occurrence->title)" required />
            <x-ui.select label="Responsável principal" name="assigned_user_id"><option value="">A definir</option>@foreach ($users as $user)<option value="{{ $user->id }}" @selected(old('assigned_user_id') == $user->id)>{{ $user->name }}</option>@endforeach</x-ui.select>
            <label class="form-field form-field--full"><span>Descrição do serviço <b>*</b></span><textarea name="description" rows="5" required>{{ old('description', $occurrence->description) }}</textarea>@error('description')<em>{{ $message }}</em>@enderror</label>
            <x-ui.input label="Prazo previsto" name="due_date" type="date" :value="old('due_date')" />
            <x-ui.input label="Data planejada" name="planned_at" type="date" :value="old('planned_at')" />
            <x-ui.input label="Custo estimado (R$)" name="estimated_cost" type="text" inputmode="numeric" autocomplete="off" data-currency-input :value="old('estimated_cost', '0.00')" />
            <label class="form-field form-field--full"><span>Observações</span><textarea name="notes" rows="3">{{ old('notes') }}</textarea></label>
        </div>
        <div class="section-title section-title--spaced"><div><h2>Condições financeiras</h2><p>Itens especiais sempre enviam a OS para aprovação.</p></div></div>
        <div class="choice-grid choice-grid--wide">
            @foreach (['requires_purchase' => ['Necessita compra', 'Indica necessidade de aquisição.'], 'external_service' => ['Serviço externo', 'Sempre exige aprovação.'], 'asset_replacement' => ['Substituição de patrimônio', 'Sempre exige aprovação.'], 'asset_disposal' => ['Descarte de ativo', 'Sempre exige aprovação.'], 'extraordinary_purchase' => ['Compra extraordinária', 'Sempre exige aprovação.']] as $name => [$label, $hint])
                <label class="checkbox-field"><input type="checkbox" name="{{ $name }}" value="1" @checked(old($name))><span><strong>{{ $label }}</strong><small>{{ $hint }}</small></span></label>
            @endforeach
        </div>
        <div class="section-title section-title--spaced"><div><h2>Fornecedor externo</h2><p>Preencha quando a condição “Serviço externo” estiver marcada. O fornecedor não recebe acesso ao SIGME.</p></div></div>
        <div class="form-grid">
            <x-ui.input label="Nome ou empresa" name="external_provider_name" :value="old('external_provider_name')" hint="Obrigatório para serviço externo." />
            <x-ui.input label="Contato" name="external_provider_contact" :value="old('external_provider_contact')" hint="Opcional: telefone ou e-mail, somente quando necessário." />
            <x-ui.input label="CNPJ ou CPF" name="external_provider_tax_id" :value="old('external_provider_tax_id')" hint="Opcional; informe somente quando necessário." />
            <label class="form-field form-field--full"><span>Descrição do serviço externo</span><textarea name="external_service_description" rows="4">{{ old('external_service_description') }}</textarea><small>Obrigatória para serviço externo.</small>@error('external_service_description')<em>{{ $message }}</em>@enderror</label>
        </div>
        <div class="form-actions"><x-ui.button variant="primary" type="submit">Criar Ordem de Serviço</x-ui.button><x-ui.button :href="route('occurrences.show', $occurrence)">Cancelar</x-ui.button></div>
    </form>
</x-layouts.app>
