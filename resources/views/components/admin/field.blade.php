@props(['name', 'label', 'hint' => null])

<div class="flex flex-col gap-1">
    <label for="{{ $name }}" class="text-sm font-medium text-slate-700">{{ $label }}</label>
    {{ $slot }}
    @if ($hint)
        <p class="text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>
