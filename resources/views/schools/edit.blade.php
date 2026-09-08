<x-layouts.app title="Editar escola" active="schools">
    <header class="page-header"><div><h1>Editar escola</h1><p>{{ $school->name }}</p></div></header>
    <form method="POST" action="{{ route('schools.update', $school) }}" class="form-card">
        @csrf
        @method('PUT')
        @include('schools._form')
    </form>
</x-layouts.app>
