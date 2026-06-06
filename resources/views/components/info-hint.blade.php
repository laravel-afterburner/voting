@props([
    'label',
    'text' => null,
    'maxWidth' => null,
    'width' => null,
    'align' => 'start',
    'scrollable' => false,
])

@php
    $widthClass = $width ?? $maxWidth ?? ($scrollable ? 'max-w-md' : 'max-w-sm');
    $panelClasses = trim(implode(' ', array_filter([
        'fixed z-[120] max-h-[calc(100vh-1rem)] max-w-[calc(100vw-1rem)] overflow-y-auto',
        $width ? $widthClass : 'w-max min-w-[12rem] '.$widthClass,
        'rounded-lg border border-gray-200 bg-white p-3 shadow-lg',
        'dark:border-gray-700 dark:bg-gray-800',
    ])));
    $contentClasses = $scrollable ? 'max-h-60 overflow-y-auto pr-1' : '';
@endphp

<span
    {{ $attributes->merge(['class' => 'relative inline-flex shrink-0']) }}
    x-data="{
        tooltipOpen: false,
        panelTop: 0,
        panelLeft: 0,
        panelMaxWidth: window.innerWidth - 16,
        align: @js($align),
        scrollHandler: null,
        positionPanel() {
            const padding = 8;
            const gap = 4;
            const rect = this.$refs.trigger.getBoundingClientRect();
            const panel = this.$refs.panel;

            if (! panel) {
                this.panelTop = rect.bottom + gap;
                this.panelLeft = Math.max(padding, rect.left);
                this.panelMaxWidth = window.innerWidth - (padding * 2);

                return;
            }

            if (panel.offsetWidth === 0 || panel.offsetHeight === 0) {
                requestAnimationFrame(() => this.positionPanel());

                return;
            }

            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            const maxPanelWidth = viewportWidth - (padding * 2);

            this.panelMaxWidth = maxPanelWidth;

            const panelWidth = panel.offsetWidth;
            const panelHeight = panel.offsetHeight;

            let left = this.align === 'end'
                ? rect.right - panelWidth
                : rect.left;

            left = Math.min(left, viewportWidth - panelWidth - padding);
            left = Math.max(padding, left);

            let top = rect.bottom + gap;

            if (top + panelHeight > viewportHeight - padding) {
                const aboveTop = rect.top - panelHeight - gap;

                top = aboveTop >= padding
                    ? aboveTop
                    : Math.max(padding, viewportHeight - panelHeight - padding);
            }

            this.panelTop = top;
            this.panelLeft = left;
        },
        toggle() {
            this.tooltipOpen = ! this.tooltipOpen;
            if (this.tooltipOpen) {
                this.$nextTick(() => this.positionPanel());
            }
        },
    }"
    x-init="
        $watch('tooltipOpen', (open) => {
            if (open) {
                positionPanel();
                scrollHandler = () => positionPanel();
                document.addEventListener('scroll', scrollHandler, true);
            } else if (scrollHandler) {
                document.removeEventListener('scroll', scrollHandler, true);
                scrollHandler = null;
            }
        });
        $el.addEventListener('alpine:destroy', () => {
            if (scrollHandler) {
                document.removeEventListener('scroll', scrollHandler, true);
            }
        });
    "
    @click.away="tooltipOpen = false"
    @resize.window="if (tooltipOpen) { positionPanel() }"
>
    <button
        type="button"
        x-ref="trigger"
        @click="toggle()"
        class="inline-flex rounded-full text-gray-500 hover:text-gray-600 focus:outline-none active:text-gray-500 dark:text-gray-400 dark:hover:text-gray-300 dark:active:text-gray-400"
        aria-label="{{ $label }}"
        :aria-expanded="tooltipOpen"
    >
        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
    </button>
    <template x-teleport="body">
        <div
            x-show="tooltipOpen"
            x-cloak
            x-transition
            x-ref="panel"
            @click.stop
            role="tooltip"
            class="{{ $panelClasses }}"
            :style="`top: ${panelTop}px; left: ${panelLeft}px; max-width: ${panelMaxWidth}px;`"
        >
            <div @class([$contentClasses => $scrollable])>
                @if (filled($text))
                    <p class="text-xs leading-relaxed text-wrap text-gray-600 dark:text-gray-400">{{ $text }}</p>
                @else
                    <div class="space-y-2 text-xs leading-relaxed text-wrap text-gray-600 dark:text-gray-400">
                        {{ $slot }}
                    </div>
                @endif
            </div>
        </div>
    </template>
</span>
