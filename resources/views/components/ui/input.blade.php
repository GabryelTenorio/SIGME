@props(['label', 'name', 'type' => 'text', 'value' => '', 'required' => false, 'hint' => null])

<label class="form-field">
    <span>{{ $label }} @if ($required)<b aria-hidden="true">*</b>@endif</span>
    <input
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        @required($required)
        {{ $attributes }}
    >
    @if ($hint)<small>{{ $hint }}</small>@endif
    @error($name)<em>{{ $message }}</em>@enderror
</label>
