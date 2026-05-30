@props([
    'options' => [],
    'selected' => [],
    'id' => 'electorate',
])

@php
    $selectedLabels = collect($options)
        ->whereIn('value', $selected)
        ->pluck('label')
        ->all();

    $summary = match (count($selectedLabels)) {
        0 => 'Select electorate…',
        1 => $selectedLabels[0],
        default => count($selectedLabels).' selected',
    };
@endphp

<div class="relative" x-data="{ open: false }" @click.away="open = false">
    <button
        type="button"
        id="{{ $id }}"
        @click="open = ! open"
        @keydown.escape.window="open = false"
        aria-haspopup="listbox"
        :aria-expanded="open"
        class="mt-1 flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-white py-2 pl-3 pr-3 text-base leading-normal text-gray-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
    >
        <span class="truncate">{{ $summary }}</span>
        <svg class="size-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    <div
        x-show="open"
        x-cloak
        @click.stop
        class="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-900"
    >
        @foreach ($options as $option)
            <label class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-gray-900 hover:bg-gray-50 dark:text-gray-100 dark:hover:bg-gray-800">
                <input
                    type="checkbox"
                    wire:model.live="electorate"
                    value="{{ $option['value'] }}"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                />
                <span>{{ $option['label'] }}</span>
            </label>
        @endforeach
    </div>
</div>
