@props([
    'text',
])

<span {{ $attributes->merge(['class' => 'group relative inline-flex shrink-0']) }}>
    <button
        type="button"
        class="rounded text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:text-gray-500 dark:hover:text-gray-300 dark:focus:ring-offset-gray-800"
        aria-label="{{ $text }}"
    >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
        </svg>
    </button>
    <span
        role="tooltip"
        class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden w-56 -translate-x-1/2 rounded-md bg-gray-900 px-3 py-2 text-left text-xs font-normal normal-case tracking-normal text-white shadow-lg group-hover:block group-focus-within:block dark:bg-gray-700"
    >
        {{ $text }}
    </span>
</span>
