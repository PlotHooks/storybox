@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-amber-400']) }}>
        {{ $status }}
    </div>
@endif
