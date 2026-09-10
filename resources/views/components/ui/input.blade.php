@props(['label', 'name', 'type' => 'text', 'value' => '', 'required' => false, 'hint' => null])

<label class="form-field">
    <span>{{ $label }} @if ($required)<b aria-hidden="true">*</b>@endif</span>
    <input
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        @required($required)
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
        @if ($errors->has($name)) aria-describedby="{{ str($name)->slug() }}-error" @endif
        {{ $attributes }}
    >
    @if ($hint)<small>{{ $hint }}</small>@endif
    @error($name)<em id="{{ str($name)->slug() }}-error">{{ $message }}</em>@enderror
</label>
