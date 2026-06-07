<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $character->name }} Profile | Storybox</title>
        <style>
            html,
            body {
                margin: 0;
                padding: 0;
                width: 100%;
                min-height: 100vh;
                background: transparent;
            }

            .advanced-profile-viewport {
                width: 100vw;
                min-height: 100vh;
                margin: 0;
                padding: 0;
            }

            .advanced-profile-viewport iframe {
                display: block;
                width: 100vw;
                height: 100vh;
                min-height: 100vh;
                margin: 0;
                padding: 0;
                border: 0;
                background: transparent;
            }
        </style>
    </head>
    <body>
        @php
            $frameSrc = $isPreview
                ? route('characters.profile.preview.frame', [$character, 'token' => $previewToken])
                : route('characters.profile.frame', $character);
        @endphp

        <div class="advanced-profile-viewport" data-advanced-profile-viewport>
            <iframe
                title="{{ $character->name }} profile"
                src="{{ $frameSrc }}"
                sandbox="allow-scripts"
                referrerpolicy="no-referrer"
            ></iframe>
        </div>
    </body>
</html>
