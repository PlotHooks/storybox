<ul class="divide-y divide-gray-700">
    @foreach ($rooms as $room)
        <li class="py-3 flex items-center justify-between">
            <div>
                <a href="{{ route('rooms.show', $room->slug) }}"
                   class="text-sm font-semibold text-teal-400 hover:text-teal-200">
                    {{ $room->name }}
                </a>
                @if ($room->description)
                    <div class="text-xs text-gray-400">
                        {{ $room->description }}
                    </div>
                @endif
            </div>
            <div class="text-xs text-gray-500">
                by {{ optional($room->user)->name ?? 'Unknown' }}
            </div>
        </li>
    @endforeach
</ul>
