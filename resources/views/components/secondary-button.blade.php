<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-[#141416] border border-[#332817] rounded-md font-semibold text-xs text-[#d6c8ad] uppercase tracking-widest shadow-sm hover:border-amber-500/50 hover:bg-[#191511] hover:text-[#f2dfb5] focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-[#050505] disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
