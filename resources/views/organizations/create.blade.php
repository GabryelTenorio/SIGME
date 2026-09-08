<x-layouts.app title="Nova organização" active="organizations">
    <header class="page-header"><div><h1>Nova organização</h1><p>Cadastre uma escola independente ou uma rede com várias unidades.</p></div></header>
    <form method="POST" action="{{ route('organizations.store') }}" class="form-card">
        @csrf
        @include('organizations._form')
    </form>
</x-layouts.app>
