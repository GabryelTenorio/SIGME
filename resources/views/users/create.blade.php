<x-layouts.app title="Novo usuário" active="users">
    <header class="page-header"><div><h1>Novo usuário</h1><p>Uma pessoa pode acumular perfis e atuar em uma ou mais escolas.</p></div></header>
    <form method="POST" action="{{ route('users.store') }}" class="form-card">
        @csrf
        @include('users._form')
    </form>
</x-layouts.app>
