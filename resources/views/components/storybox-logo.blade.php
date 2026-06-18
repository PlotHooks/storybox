@props(['title' => 'Storybox'])

<img
    src="{{ asset('images/storybox-icon.png') }}"
    alt="{{ $title }}"
    title="{{ $title }}"
    {{ $attributes->merge(['class' => 'object-contain']) }}
>
