@php
    use App\Models\User;

    $soundOptions = User::dmNotificationSoundOptions();
    $selectedSound = old('dm_notification_sound_choice', $user->dm_notification_sound_choice ?: User::DM_NOTIFICATION_SOUND_DEFAULT);
    $customSoundUrl = old('dm_notification_sound_url', $user->dm_notification_sound_url);
    $soundEnabled = $selectedSound !== User::DM_NOTIFICATION_SOUND_OFF;
@endphp

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('DM Notification Sound') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Choose the sound played for new unread direct messages. This temporary profile section will move into a future global settings window.') }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6" data-dm-sound-form>
        @csrf
        @method('patch')

        <input type="hidden" name="name" value="{{ $user->name }}">
        <input type="hidden" name="email" value="{{ $user->email }}">
        <input type="hidden" name="dm_notification_sound_enabled" value="{{ $soundEnabled ? '1' : '0' }}" data-dm-sound-enabled>

        <div>
            <x-input-label for="dm_notification_sound_choice" :value="__('Sound Choice')" />
            <select
                id="dm_notification_sound_choice"
                name="dm_notification_sound_choice"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                data-dm-sound-choice
            >
                @foreach ($soundOptions as $value => $label)
                    <option value="{{ $value }}" @selected($selectedSound === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('dm_notification_sound_choice')" />
        </div>

        <div>
            <x-input-label for="dm_notification_sound_url" :value="__('Custom Hosted Sound URL')" />
            <x-text-input
                id="dm_notification_sound_url"
                name="dm_notification_sound_url"
                type="url"
                class="mt-1 block w-full"
                :value="$customSoundUrl"
                placeholder="https://cdn.example.com/notify.mp3"
                maxlength="2048"
                data-dm-sound-url
            />
            <p class="mt-1 text-sm text-gray-600">
                {{ __('No uploads. Custom sounds must be hosted elsewhere and use a common audio file extension such as .mp3, .ogg, .wav, .m4a, .aac, or .webm.') }}
            </p>
            <x-input-error class="mt-2" :messages="$errors->get('dm_notification_sound_url')" />
        </div>

        <div class="flex items-center gap-4">
            <button
                type="button"
                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                data-dm-sound-preview
            >
                {{ __('Preview Sound') }}
            </button>

            <x-primary-button>{{ __('Save Sound Preference') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>

<script>
(function () {
    const form = document.querySelector('[data-dm-sound-form]');
    if (!form) return;

    const choiceInput = form.querySelector('[data-dm-sound-choice]');
    const urlInput = form.querySelector('[data-dm-sound-url]');
    const enabledInput = form.querySelector('[data-dm-sound-enabled]');
    const previewButton = form.querySelector('[data-dm-sound-preview]');
    const customChoice = @json(User::DM_NOTIFICATION_SOUND_CUSTOM);
    const offChoice = @json(User::DM_NOTIFICATION_SOUND_OFF);
    const supportedExtensions = ['mp3', 'ogg', 'wav', 'm4a', 'aac', 'webm'];
    let audioContext = null;

    function getAudioContext() {
        if (!window.AudioContext && !window.webkitAudioContext) return null;
        if (!audioContext) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            audioContext = new AudioContextClass();
        }

        return audioContext;
    }

    function isSafeHostedAudioUrl(url) {
        if (!url) return false;

        try {
            const parsed = new URL(url, window.location.origin);
            if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
                return false;
            }

            const pathname = parsed.pathname || '';
            const extension = pathname.includes('.') ? pathname.split('.').pop().toLowerCase() : '';
            return supportedExtensions.includes(extension);
        } catch (error) {
            return false;
        }
    }

    async function ensureAudioContext() {
        const context = getAudioContext();
        if (!context) {
            throw new Error('Audio context unavailable');
        }

        if (context.state === 'suspended') {
            await context.resume();
        }

        if (context.state !== 'running') {
            throw new Error('Audio playback unavailable');
        }

        return context;
    }

    function scheduleTone(context, destination, oscillatorType, frequency, startAt, duration, gainValue) {
        const oscillator = context.createOscillator();
        const gainNode = context.createGain();

        oscillator.type = oscillatorType;
        oscillator.frequency.setValueAtTime(frequency, startAt);
        gainNode.gain.setValueAtTime(0.0001, startAt);
        gainNode.gain.exponentialRampToValueAtTime(gainValue, startAt + 0.01);
        gainNode.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);

        oscillator.connect(gainNode);
        gainNode.connect(destination);

        oscillator.start(startAt);
        oscillator.stop(startAt + duration + 0.02);
    }

    async function playBuiltInPreview(choice) {
        const context = await ensureAudioContext();
        const now = context.currentTime + 0.02;
        const master = context.createGain();
        master.gain.value = 0.12;
        master.connect(context.destination);

        if (choice === 'soft_chime') {
            scheduleTone(context, master, 'sine', 587.33, now, 0.32, 0.18);
            scheduleTone(context, master, 'sine', 783.99, now + 0.16, 0.48, 0.16);
            return;
        }

        if (choice === 'bell') {
            scheduleTone(context, master, 'triangle', 659.25, now, 0.52, 0.2);
            scheduleTone(context, master, 'sine', 987.77, now + 0.02, 0.68, 0.08);
            return;
        }

        if (choice === 'click_tick') {
            scheduleTone(context, master, 'square', 1046.5, now, 0.045, 0.09);
            scheduleTone(context, master, 'square', 880, now + 0.12, 0.045, 0.08);
            return;
        }

        scheduleTone(context, master, 'triangle', 523.25, now, 0.18, 0.14);
        scheduleTone(context, master, 'triangle', 783.99, now + 0.14, 0.28, 0.14);
    }

    async function playCustomPreview(url) {
        if (!isSafeHostedAudioUrl(url)) {
            throw new Error('Enter a valid hosted audio URL first.');
        }

        const audio = new Audio(url);
        audio.preload = 'none';
        audio.volume = 0.8;
        await audio.play();
    }

    function syncFormState() {
        const choice = choiceInput?.value || offChoice;
        const customSelected = choice === customChoice;
        const enabled = choice !== offChoice;

        if (enabledInput) {
            enabledInput.value = enabled ? '1' : '0';
        }

        if (urlInput) {
            urlInput.disabled = !customSelected;
        }

        if (previewButton) {
            previewButton.disabled = !enabled;
        }
    }

    choiceInput?.addEventListener('change', syncFormState);
    syncFormState();

    previewButton?.addEventListener('click', async () => {
        const choice = choiceInput?.value || offChoice;
        if (choice === offChoice) return;

        try {
            if (choice === customChoice) {
                await playCustomPreview(urlInput?.value || '');
                return;
            }

            await playBuiltInPreview(choice);
        } catch (error) {
            if (choice === customChoice && urlInput) {
                urlInput.reportValidity();
            }
        }
    });
})();
</script>
