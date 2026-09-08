<x-layouts.app title="Editar organização" active="organizations">
    <header class="page-header"><div><h1>Editar organização</h1><p>{{ $organization->name }}</p></div></header>
    <form method="POST" action="{{ route('organizations.update', $organization) }}" class="form-card">
        @csrf
        @method('PUT')
        @include('organizations._form')
    </form>
</x-layouts.app>
