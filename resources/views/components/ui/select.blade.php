@props(['label', 'name', 'required' => false, 'hint' => null])

<label class="form-field">
    <span>{{ $label }} @if ($required)<b aria-hidden="true">*</b>@endif</span>
    <select name="{{ $name }}" @required($required) {{ $attributes }}>{{ $slot }}</select>
    @if ($hint)<small>{{ $hint }}</small>@endif
    @error($name)<em>{{ $message }}</em>@enderror
</label>
