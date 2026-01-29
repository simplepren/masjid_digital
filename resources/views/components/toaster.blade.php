<div>
    <div id="toast-container"
        x-data="{
            toasts: [],
            init() {
                // Ambil flash session saat page load (full reload)
                const flashElement = document.getElementById('flash-toast-data');
                if (flashElement) {
                    this.showToast({
                        type: flashElement.dataset.type,
                        message: flashElement.dataset.message,
                        timeout: parseInt(flashElement.dataset.timeout)
                    });
                    flashElement.remove();
                }
            },
            showToast(toastData) {
                let id = Math.random().toString(36).substring(2, 9);
                this.toasts.push({
                    id: id,
                    message: toastData.message,
                    type: toastData.type || 'info'
                });
                setTimeout(() => {
                    this.toasts = this.toasts.filter(toast => toast.id !== id);
                }, toastData.timeout || 4000);
            }
        }"
        @toast.window="showToast($event.detail)"
        class="fixed z-50 flex flex-col space-y-2 
               bottom-0 left-0 right-0 px-2 
               lg:bottom-3 lg:right-3 lg:left-auto lg:w-auto lg:max-w-sm">

        <template x-for="toast in toasts" :key="toast.id">
            <div
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-300"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-4"
                :class="{
                    'bg-green-700': toast.type === 'success',
                    'bg-red-600': toast.type === 'error',
                    'bg-blue-600': toast.type === 'info',
                    'bg-yellow-600': toast.type === 'warning'
                }"
                class="rounded-t-lg lg:rounded-lg shadow-lg text-white p-4 
                       w-full lg:w-auto lg:max-w-sm">
                <div class="flex items-center">
                    <div class="flex-shrink-0 mr-3">
                        <span x-show="toast.type === 'success'"><flux:icon.check-circle/></span>
                        <span x-show="toast.type === 'error'"><flux:icon.octagon-x/></span>
                        <span x-show="toast.type === 'info'"><flux:icon.info/></span>
                        <span x-show="toast.type === 'warning'"><flux:icon.octagon-alert/></span>
                    </div>
                    <p x-text="toast.message" class="flex-1"></p>
                    <button @click="toasts = toasts.filter(t => t.id !== toast.id)"
                        class="ml-3 text-white hover:text-gray-200">
                        ×
                    </button>
                </div>
            </div>
        </template>
    </div>
</div>
