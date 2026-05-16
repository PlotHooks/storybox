@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-amber-400 text-start text-base font-semibold text-[#f2dfb5] bg-amber-500/10 focus:outline-none focus:text-[#f2dfb5] focus:bg-amber-500/15 focus:border-amber-300 transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-[#8f8675] hover:text-[#f2dfb5] hover:bg-[#141416] hover:border-[#5a431f] focus:outline-none focus:text-[#f2dfb5] focus:bg-[#141416] focus:border-amber-400 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
