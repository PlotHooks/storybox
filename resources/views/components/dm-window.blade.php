<div
    id="dm-window"
    class="hidden fixed z-50 bg-gray-900 border border-gray-700 rounded-lg shadow-2xl flex flex-col"
    style="
        width: 420px;
        height: 520px;
        top: 120px;
        right: 40px;
    "
>

    <!-- HEADER (drag handle) -->
    <div
        id="dm-drag-handle"
        class="cursor-move flex items-center justify-between px-3 py-2 border-b border-gray-800 bg-gray-950 rounded-t-lg"
    >
        <div class="text-sm text-gray-200 font-semibold">
            Direct Messages
        </div>

        <div class="flex gap-2">
            <button
                onclick="document.getElementById('dm-window').classList.add('hidden')"
                class="text-gray-400 hover:text-white text-sm"
            >
                ✕
            </button>
        </div>
    </div>

    <!-- BODY -->
    <div class="flex-1 flex overflow-hidden">

        <!-- LEFT: conversation list placeholder -->
        <div class="w-40 border-r border-gray-800 overflow-y-auto text-xs text-gray-300">
            <div class="p-2 text-gray-500">
                Conversations will live here.
            </div>
        </div>

        <!-- RIGHT: message area placeholder -->
        <div class="flex-1 flex flex-col">
            <div class="flex-1 p-3 text-sm text-gray-400 overflow-y-auto">
                Select a conversation.
            </div>

            <div class="border-t border-gray-800 p-2">
                <textarea
                    class="w-full resize-none rounded bg-gray-950 border-gray-700 text-gray-200 text-sm"
                    rows="2"
                    placeholder="Message..."
                ></textarea>
            </div>
        </div>

    </div>

    <!-- RESIZE HANDLE -->
    <div
        id="dm-resize"
        class="absolute bottom-0 right-0 w-4 h-4 cursor-se-resize"
    ></div>

</div>


<script>
/*
|--------------------------------------------------------------------------
| DM WINDOW CONTROLLER
|--------------------------------------------------------------------------
*/

(function () {

    const dmWindow = document.getElementById('dm-window');

    if (!dmWindow) return;

    /*
    |--------------------------------------------------------------------------
    | OPEN EVENT
    |--------------------------------------------------------------------------
    */

    window.addEventListener('open-dm-window', () => {
        dmWindow.classList.remove('hidden');
    });

    /*
    |--------------------------------------------------------------------------
    | DRAG
    |--------------------------------------------------------------------------
    */

    const dragHandle = document.getElementById('dm-drag-handle');

    let isDragging = false;
    let offsetX = 0;
    let offsetY = 0;

    dragHandle.addEventListener('mousedown', (e) => {
        isDragging = true;

        offsetX = e.clientX - dmWindow.offsetLeft;
        offsetY = e.clientY - dmWindow.offsetTop;

        document.body.style.userSelect = 'none';
    });

    document.addEventListener('mouseup', () => {
        isDragging = false;
        document.body.style.userSelect = '';
    });

    document.addEventListener('mousemove', (e) => {
        if (!isDragging) return;

        dmWindow.style.left = (e.clientX - offsetX) + 'px';
        dmWindow.style.top = (e.clientY - offsetY) + 'px';

        dmWindow.style.right = 'auto';
    });

    /*
    |--------------------------------------------------------------------------
    | RESIZE
    |--------------------------------------------------------------------------
    */

    const resizeHandle = document.getElementById('dm-resize');

    let isResizing = false;

    resizeHandle.addEventListener('mousedown', () => {
        isResizing = true;
        document.body.style.userSelect = 'none';
    });

    document.addEventListener('mouseup', () => {
        isResizing = false;
        document.body.style.userSelect = '';
    });

    document.addEventListener('mousemove', (e) => {
        if (!isResizing) return;

        dmWindow.style.width = (e.clientX - dmWindow.offsetLeft) + 'px';
        dmWindow.style.height = (e.clientY - dmWindow.offsetTop) + 'px';
    });

})();
</script>
