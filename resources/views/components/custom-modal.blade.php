@props([
    'dismissible' => true, // Default: true (boolean)
    'position' => null,
    'closable' => true,    // Default: true (boolean)
    'trigger' => null,
    'variant' => null,
    'name' => null,
    'width' => 'w-2xl',
])

@php
    // Pastikan $dismissible dan $closable selalu boolean
    $dismissible = (bool) $dismissible;
    $closable = (bool) $closable;

    // Logika closable default berdasarkan variant 'bare'
    // Ini harus dilakukan setelah memastikan $closable adalah boolean
    if ($variant === 'bare') {
        $closable = false; // Jika variant bare, tombol close tidak ada
    }

    $contentClasses = [
        'relative',
        'z-index: 50;',
        $width,
    ];

    $contentClasses[] = match ($variant) {
        default => 'p-6 shadow-lg rounded-xl',
        'flyout' => match($position) {
            'bottom' => 'fixed m-0 p-8 min-w-[100vw] overflow-y-auto mt-auto [--fx-flyout-translate:translateY(50px)] border-t',
            'left' => 'fixed m-0 p-8 max-h-dvh min-h-dvh md:[:where(&)]:min-w-[25rem] overflow-y-auto mr-auto [--fx-flyout-translate:translateX(-50px)] border-e rtl:mr-0 rtl:ml-auto rtl:[--fx-flyout-translate:50px]',
            default => 'fixed m-0 p-8 max-h-dvh min-h-dvh md:[:where(&)]:min-w-[25rem] overflow-y-auto ml-auto [--fx-flyout-translate:translateX(50px)] border-s rtl:ml-0 rtl:mr-auto rtl:[--fx-flyout-translate:-50px]',
        },
        'bare' => '',
    };

    $contentClasses[] = match ($variant) {
        default => 'bg-white dark:bg-zinc-800 border border-transparent dark:border-zinc-700',
        'flyout' => 'bg-white dark:bg-zinc-800 border-transparent dark:border-zinc-700',
        'bare' => 'bg-transparent',
    };

    $overlayZIndex = 'z-index: 40;'; 
@endphp

<div 
    x-data="{
        open: false,
        name: @js($name),
        // Sekarang $dismissible dan $closable dijamin boolean
        isDismissible: @js($dismissible), 
        isClosable: @js($closable),
        
        show: function() {
            this.open = true;
            document.body.style.overflow = 'hidden';
        },
        hide: function() {
            this.open = false;
            document.body.style.overflow = '';
        },
        handleOutsideClick: function() {
            if (this.isDismissible) {
                this.hide();
            }
        },
        handleClose: function() {
            if (this.isClosable) {
                this.hide();
            }
        }
    }"
    x-cloak
    @keydown.escape.window="handleClose()"
    x-on:open-modal.window="if ($event.detail.name === name) { show() }"
    x-on:close-modal.window="if ($event.detail.name === name) { hide() }"
>
    <?php if ($trigger): ?>
        {{ $trigger }}
    <?php endif; ?>

    {{-- Overlay Modal --}}
    <div 
        x-show="open" 
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/50 flex items-center justify-center transition-opacity"
        style="{{ $overlayZIndex }}"
        x-on:click="handleOutsideClick()"
    >
        {{-- Konten Modal --}}
        <div 
            x-show="open"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="{{ implode(' ', $contentClasses) }}" 
            x-on:click.stop=""
            style="min-width: 20rem; min-height: 10rem;"
            x-ref="modalContent"
        >
            {{ $slot }}

            {{-- Tombol Tutup --}}
            <?php if ($closable): ?>
                <div class="absolute top-0 end-0 mt-4 me-4" style="z-index: 51;">
                    <button type="button" x-on:click="handleClose()"
                        class="text-zinc-400! hover:text-zinc-800! dark:text-zinc-500! dark:hover:text-white! p-1 rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>