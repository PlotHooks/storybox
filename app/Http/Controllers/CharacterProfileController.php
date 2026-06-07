<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\CharacterProfile;
use App\Models\CharacterProfileRevision;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CharacterProfileController extends Controller
{
    private const PREVIEW_SESSION_KEY = 'character_profile_previews';

    public function show(Character $character): View
    {
        return $this->renderProfilePage($character, $character->ensureProfile());
    }

    public function frame(Character $character): Response
    {
        $profile = $character->ensureProfile();

        abort_unless($profile->shouldRenderCustomProfile(), 404);

        return $this->frameResponse(
            view('character_profiles.custom-frame', [
                'customDocument' => $this->buildCustomDocument(
                    $character,
                    $profile->custom_html,
                    $profile->custom_css,
                    $profile->custom_js,
                ),
            ])
        );
    }

    public function edit(Character $character): View
    {
        $this->authorizeEditor($character);

        return view('character_profiles.edit', [
            'character' => $character,
            'profile' => $character->ensureProfile(),
            'externalLinks' => $this->editorExternalLinks($character->ensureProfile()),
        ]);
    }

    public function update(Request $request, Character $character): RedirectResponse
    {
        $this->authorizeEditor($character);

        $profile = $character->ensureProfile();
        $payload = $this->validatedPayload($request);

        if ($this->customCodeChanged($profile, $payload)) {
            $profile->createRevisionSnapshot();
        }

        $profile->fill($payload)->save();

        return redirect()
            ->route('characters.profile.edit', $character)
            ->with('status', 'Character profile updated.');
    }

    public function preview(Request $request, Character $character): RedirectResponse
    {
        $this->authorizeEditor($character);

        $token = (string) Str::uuid();
        $preview = $this->validatedPayload($request);

        $request->session()->put(self::PREVIEW_SESSION_KEY.'.'.$token, [
            'user_id' => (int) $request->user()->id,
            'character_id' => (int) $character->id,
            'payload' => $preview,
            'created_at' => now()->timestamp,
        ]);

        return redirect()->route('characters.profile.preview.show', [$character, 'token' => $token]);
    }

    public function previewShow(Request $request, Character $character, string $token): View
    {
        $this->authorizeEditor($character);

        $preview = $this->previewPayload($request, $character, $token);
        $profile = $character->ensureProfile()->replicate();
        $profile->exists = false;
        $profile->fill($preview);

        return $this->renderProfilePage($character, $profile, true, $token);
    }

    public function previewFrame(Request $request, Character $character, string $token): Response
    {
        $this->authorizeEditor($character);

        $preview = $this->previewPayload($request, $character, $token);
        $profile = $character->ensureProfile()->replicate();
        $profile->exists = false;
        $profile->fill($preview);

        abort_unless($profile->shouldRenderCustomProfile(), 404);

        return $this->frameResponse(
            view('character_profiles.custom-frame', [
                'customDocument' => $this->buildCustomDocument(
                    $character,
                    $profile->custom_html,
                    $profile->custom_css,
                    $profile->custom_js,
                ),
            ]),
            true
        );
    }

    public function revisions(Character $character): View
    {
        $this->authorizeEditor($character);

        $profile = $character->ensureProfile()->load('revisions');

        return view('character_profiles.revisions', [
            'character' => $character,
            'profile' => $profile,
            'revisions' => $profile->revisions,
        ]);
    }

    public function restoreRevision(Character $character, CharacterProfileRevision $revision): RedirectResponse
    {
        $this->authorizeEditor($character);

        $profile = $character->ensureProfile();
        abort_unless((int) $revision->character_profile_id === (int) $profile->id, 404);

        if ($this->customCodeChanged($profile, [
            'custom_html' => $revision->custom_html,
            'custom_css' => $revision->custom_css,
            'custom_js' => $revision->custom_js,
        ])) {
            $profile->createRevisionSnapshot();
        }

        $profile->fill([
            'custom_html' => $revision->custom_html,
            'custom_css' => $revision->custom_css,
            'custom_js' => $revision->custom_js,
            'custom_profile_enabled' => filled($revision->custom_html) || filled($revision->custom_css) || filled($revision->custom_js),
        ])->save();

        return redirect()
            ->route('characters.profile.revisions', $character)
            ->with('status', 'Profile revision restored.');
    }

    public function disableCustom(Character $character): RedirectResponse
    {
        $this->authorizeModerator();

        $character->ensureProfile()->forceFill([
            'custom_profile_disabled_by_admin' => true,
        ])->save();

        return back()->with('status', 'Custom profile rendering disabled.');
    }

    public function enableCustom(Character $character): RedirectResponse
    {
        $this->authorizeModerator();

        $character->ensureProfile()->forceFill([
            'custom_profile_disabled_by_admin' => false,
        ])->save();

        return back()->with('status', 'Custom profile rendering re-enabled.');
    }

    public function revertToDefault(Character $character): RedirectResponse
    {
        $this->authorizeModerator();

        $character->ensureProfile()->forceFill([
            'custom_profile_enabled' => false,
        ])->save();

        return back()->with('status', 'Profile reverted to the default Storybox template.');
    }

    private function authorizeEditor(Character $character): void
    {
        $user = request()->user();

        abort_unless(
            $user !== null && (Gate::allows('own-character', $character) || (bool) ($user->is_admin ?? false)),
            403
        );
    }

    private function authorizeModerator(): void
    {
        abort_unless((bool) (request()->user()?->is_admin ?? false), 403);
    }

    private function validatedPayload(Request $request): array
    {
        $validated = $request->validate([
            'template_type' => ['required', 'string', 'max:50'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'avatar_url' => $this->externalUrlRules(),
            'banner_url' => $this->externalUrlRules(),
            'biography' => ['nullable', 'string'],
            'hooks' => ['nullable', 'string'],
            'external_links' => ['nullable', 'array'],
            'external_links.*.label' => ['nullable', 'string', 'max:100'],
            'external_links.*.url' => $this->nullableExternalUrlRules(),
            'custom_html' => ['nullable', 'string'],
            'custom_css' => ['nullable', 'string'],
            'custom_js' => ['nullable', 'string'],
            'custom_profile_enabled' => ['nullable'],
        ]);

        return [
            'template_type' => $validated['template_type'] ?? CharacterProfile::TEMPLATE_STORYBOX,
            'tagline' => $this->nullableString($validated['tagline'] ?? null),
            'avatar_url' => $this->nullableString($validated['avatar_url'] ?? null),
            'banner_url' => $this->nullableString($validated['banner_url'] ?? null),
            'biography' => $this->nullableString($validated['biography'] ?? null),
            'hooks' => $this->nullableString($validated['hooks'] ?? null),
            'external_links' => $this->normalizedExternalLinks($validated['external_links'] ?? []),
            'custom_html' => $this->nullableString($validated['custom_html'] ?? null),
            'custom_css' => $this->nullableString($validated['custom_css'] ?? null),
            'custom_js' => $this->nullableString($validated['custom_js'] ?? null),
            'custom_profile_enabled' => $request->boolean('custom_profile_enabled'),
        ];
    }

    private function normalizedExternalLinks(array $links): array
    {
        return array_values(array_filter(array_map(function (array $link): ?array {
            $label = $this->nullableString($link['label'] ?? null);
            $url = $this->nullableString($link['url'] ?? null);

            if ($label === null && $url === null) {
                return null;
            }

            if ($url === null) {
                return null;
            }

            return [
                'label' => $label ?: parse_url($url, PHP_URL_HOST) ?: $url,
                'url' => $url,
            ];
        }, $links)));
    }

    private function externalUrlRules(): array
    {
        return [
            'nullable',
            'string',
            'max:2048',
            'url',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                $scheme = parse_url($value, PHP_URL_SCHEME);

                if (! in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
                    $fail('The '.str_replace('_', ' ', $attribute).' must be an http or https URL.');
                }
            },
        ];
    }

    private function nullableExternalUrlRules(): array
    {
        return $this->externalUrlRules();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    private function customCodeChanged(CharacterProfile $profile, array $payload): bool
    {
        return ($payload['custom_html'] ?? $profile->custom_html) !== $profile->custom_html
            || ($payload['custom_css'] ?? $profile->custom_css) !== $profile->custom_css
            || ($payload['custom_js'] ?? $profile->custom_js) !== $profile->custom_js;
    }

    private function renderProfilePage(Character $character, CharacterProfile $profile, bool $isPreview = false, ?string $previewToken = null): View
    {
        if ($profile->shouldRenderCustomProfile()) {
            return view('character_profiles.show-custom', [
                'character' => $character,
                'profile' => $profile,
                'isPreview' => $isPreview,
                'previewToken' => $previewToken,
            ]);
        }

        return view('character_profiles.show-default', [
            'character' => $character,
            'profile' => $profile,
            'isPreview' => $isPreview,
        ]);
    }

    private function frameResponse(View $view, bool $isPreview = false): Response
    {
        $response = response($view)
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Content-Type-Options', 'nosniff');

        if ($isPreview) {
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        return $response;
    }


    private function buildCustomDocument(Character $character, ?string $customHtml, ?string $customCss, ?string $customJs): string
    {
        $customHtml = $customHtml ?? '';
        $customCss = $customCss ?? '';
        $customJs = $customJs ?? '';

        $baseStyle = <<<'CSS'
html, body {
    margin: 0;
    padding: 0;
    width: 100%;
    min-height: 100vh;
}
CSS;

        [$stylesheetLinks, $remainingCss] = $this->extractStylesheetImports($customCss);
        $headAssets = $stylesheetLinks.'<style>'.$baseStyle.$remainingCss.'</style>';
        $scriptBlock = $customJs !== '' ? '<script>'.$customJs.'</script>' : '';
        $trimmedHtml = ltrim($customHtml);
        $looksLikeFullDocument = preg_match('/<(?:!doctype|html|head|body)\b/i', $trimmedHtml) === 1;

        if (! $looksLikeFullDocument) {
            return '<!DOCTYPE html>'
                . '<html lang="'.e(str_replace('_', '-', app()->getLocale())).'">'
                . '<head>'
                . '<meta charset="utf-8">'
                . '<meta name="viewport" content="width=device-width, initial-scale=1">'
                . '<title>'.e($character->name.' Custom Profile').'</title>'
                . $headAssets
                . '</head>'
                . '<body>'
                . $customHtml
                . $scriptBlock
                . '</body>'
                . '</html>';
        }

        $document = $customHtml;

        if (stripos($document, '<head') !== false) {
            $document = preg_replace('/<\/head>/i', $headAssets.'</head>', $document, 1) ?? $document;
        } elseif (stripos($document, '<html') !== false) {
            $document = preg_replace('/<html([^>]*)>/i', '<html$1><head>'.$headAssets.'</head>', $document, 1) ?? $document;
        } else {
            $document = $headAssets.$document;
        }

        if ($scriptBlock !== '') {
            if (stripos($document, '</body>') !== false) {
                $document = preg_replace('/<\/body>/i', $scriptBlock.'</body>', $document, 1) ?? $document;
            } else {
                $document .= $scriptBlock;
            }
        }

        return $document;
    }

    private function extractStylesheetImports(string $customCss): array
    {
        $links = [];
        $remainingCss = preg_replace_callback(
            '/^\s*@import\s+url\(\s*(["\']?)(https?:\/\/[^)"\'\s]+)\1\s*\)\s*;?/im',
            function (array $matches) use (&$links): string {
                $url = $matches[2] ?? '';
                $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

                if (! in_array($scheme, ['http', 'https'], true)) {
                    return $matches[0];
                }

                $links[] = '<link rel="stylesheet" href="'.e($url).'">';

                return '';
            },
            $customCss,
        );

        return [implode('', $links), $remainingCss ?? $customCss];
    }

    private function editorExternalLinks(CharacterProfile $profile): array
    {
        $links = $profile->external_links ?? [];

        return $links === [] ? array_fill(0, 2, ['label' => '', 'url' => '']) : $links;
    }

    private function previewPayload(Request $request, Character $character, string $token): array
    {
        $preview = $request->session()->get(self::PREVIEW_SESSION_KEY.'.'.$token);

        abort_unless(
            is_array($preview)
            && (int) ($preview['user_id'] ?? 0) === (int) $request->user()->id
            && (int) ($preview['character_id'] ?? 0) === (int) $character->id,
            404
        );

        return $preview['payload'] ?? [];
    }
}
