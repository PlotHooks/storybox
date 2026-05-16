@props(['title' => 'Storybox'])

<svg
    viewBox="0 0 96 96"
    role="img"
    aria-label="{{ $title }}"
    xmlns="http://www.w3.org/2000/svg"
    {{ $attributes }}
>
    <title>{{ $title }}</title>
    <path
        fill="currentColor"
        d="M16 23.5 48 8l32 15.5v48.8L48 88 16 72.3V23.5Zm9 5.8v37.4l18 8.8V38.1L25 29.3Zm46 0-18 8.8v37.4l18-8.8V29.3ZM31.4 23.6 48 31.8l16.6-8.2L48 15.5 31.4 23.6Z"
    />
    <path
        fill="none"
        stroke="currentColor"
        stroke-linejoin="round"
        stroke-width="4"
        d="M48 31.8v50.5M22.5 25.8 48 38.1l25.5-12.3"
        opacity=".42"
    />
    <path
        fill="currentColor"
        d="M31 45.2 38 49v8.1l-7-3.8v-8.1Zm27-1.4 7-3.8v24.1l-7 3.8V43.8Z"
        opacity=".72"
    />
</svg>
