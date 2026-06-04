@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-md border border-amber-400/60 bg-amber-500/15 px-3 py-2 text-start text-base font-semibold text-[#fff2cc] shadow-[0_0_0_1px_rgba(245,158,11,0.18)] focus:outline-none focus:ring-2 focus:ring-amber-400/50 transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-[#8f8675] hover:text-[#f2dfb5] hover:bg-[#141416] hover:border-[#5a431f] focus:outline-none focus:text-[#f2dfb5] focus:bg-[#141416] focus:border-amber-400 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
