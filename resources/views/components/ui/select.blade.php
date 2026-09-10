@props(['label', 'name', 'required' => false, 'hint' => null])

<label class="form-field">
    <span>{{ $label }} @if ($required)<b aria-hidden="true">*</b>@endif</span>
    <select name="{{ $name }}" @required($required) aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" @if ($errors->has($name)) aria-describedby="{{ str($name)->slug() }}-error" @endif {{ $attributes }}>{{ $slot }}</select>
    @if ($hint)<small>{{ $hint }}</small>@endif
    @error($name)<em id="{{ str($name)->slug() }}-error">{{ $message }}</em>@enderror
</label>
