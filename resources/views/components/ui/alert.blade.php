@if (session('success'))
    <div class="alert alert--success" role="status"><x-ui.icon name="check" />{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert--danger" role="alert">{{ $errors->first() }}</div>
@endif
