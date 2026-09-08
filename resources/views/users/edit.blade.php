<x-layouts.app title="Editar usuário" active="users">
    <header class="page-header"><div><h1>Editar usuário</h1><p>{{ $managedUser->name }}</p></div></header>
    <form method="POST" action="{{ route('users.update', $managedUser) }}" class="form-card">
        @csrf
        @method('PUT')
        @include('users._form')
    </form>
</x-layouts.app>
