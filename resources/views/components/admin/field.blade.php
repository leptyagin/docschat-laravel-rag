@props(['name', 'label', 'hint' => null])

<div class="flex flex-col gap-1.5">
    <label for="{{ $name }}" class="text-sm font-medium text-mist">{{ $label }}</label>
    {{ $slot }}
    @if ($hint)
        <p class="text-xs text-haze">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="text-sm text-red-300">{{ $message }}</p>
    @enderror
</div>
