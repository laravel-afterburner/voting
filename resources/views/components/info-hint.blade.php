@props([
    'label',
    'text' => null,
    'maxWidth' => null,
    'align' => 'start',
    'scrollable' => false,
])

@php
    $maxWidthClass = $maxWidth ?? ($scrollable ? 'max-w-md' : 'max-w-sm');
    $alignClass = $align === 'end' ? 'right-0 left-auto' : 'left-0 right-auto';
    $panelClasses = trim(implode(' ', [
        'absolute top-full z-50 mt-1',
        $alignClass,
        'w-max min-w-[12rem]',
        $maxWidthClass,
        'rounded-lg border border-gray-200 bg-white p-3 shadow-lg',
        'dark:border-gray-700 dark:bg-gray-800',
    ]));
    $contentClasses = $scrollable ? 'max-h-60 overflow-y-auto pr-1' : '';
@endphp

<span
    {{ $attributes->merge(['class' => 'relative inline-flex shrink-0']) }}
    x-data="{ tooltipOpen: false }"
    @click.away="tooltipOpen = false"
>
    <button
        type="button"
        @click="tooltipOpen = ! tooltipOpen"
        class="inline-flex rounded-full text-gray-500 hover:text-gray-600 focus:outline-none active:text-gray-500 dark:text-gray-400 dark:hover:text-gray-300 dark:active:text-gray-400"
        aria-label="{{ $label }}"
        :aria-expanded="tooltipOpen"
    >
        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
    </button>
    <div
        x-show="tooltipOpen"
        x-cloak
        x-transition
        @click.away="tooltipOpen = false"
        role="tooltip"
        class="{{ $panelClasses }}"
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
</span>
