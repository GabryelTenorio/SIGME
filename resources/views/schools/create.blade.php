<x-layouts.app title="Nova escola" active="schools">
    <header class="page-header"><div><h1>Nova escola</h1><p>Vincule uma unidade a uma organização existente.</p></div></header>
    <form method="POST" action="{{ route('schools.store') }}" class="form-card">
        @csrf
        @include('schools._form')
    </form>
</x-layouts.app>
