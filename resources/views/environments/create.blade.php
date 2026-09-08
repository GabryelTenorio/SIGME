<x-layouts.app title="Novo ambiente" active="environments">
    <header class="page-header"><div><h1>Novo ambiente</h1><p>Cadastre um local válido dentro da escola selecionada.</p></div></header>
    <form method="POST" action="{{ route('environments.store') }}" class="form-card">@csrf @include('environments._form')</form>
</x-layouts.app>
