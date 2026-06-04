@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center rounded-md border border-amber-400/70 bg-amber-500/15 px-3 py-2 text-sm font-semibold leading-5 text-[#fff2cc] shadow-[0_0_0_1px_rgba(245,158,11,0.2),0_0_18px_rgba(245,158,11,0.16)] focus:outline-none focus:ring-2 focus:ring-amber-400/50 transition duration-150 ease-in-out'
            : 'inline-flex items-center rounded-md border border-transparent px-3 py-2 text-sm font-medium leading-5 text-[#8f8675] hover:border-[#5a431f] hover:bg-[#141416] hover:text-[#f2dfb5] focus:outline-none focus:ring-2 focus:ring-amber-400/40 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
