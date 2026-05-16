@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-[#d6c8ad]']) }}>
    {{ $value ?? $slot }}
</label>
