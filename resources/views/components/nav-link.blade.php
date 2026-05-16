@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-amber-400 text-sm font-semibold leading-5 text-[#f2dfb5] shadow-[0_1px_12px_rgba(245,158,11,0.22)] focus:outline-none focus:border-amber-300 transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-[#8f8675] hover:text-[#f2dfb5] hover:border-[#5a431f] focus:outline-none focus:text-[#f2dfb5] focus:border-amber-400 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
