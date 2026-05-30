@props([
    'text',
])

<span {{ $attributes->merge(['class' => 'group relative inline-flex shrink-0']) }}>
    <button
        type="button"
        class="rounded text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:text-gray-500 dark:hover:text-gray-300 dark:focus:ring-offset-gray-800"
        aria-label="{{ $text }}"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true">
            <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm-1 9a1 1 0 0 0 2 0v-4a1 1 0 0 0-2 0v4Z" clip-rule="evenodd" />
        </svg>
    </button>
    <span
        role="tooltip"
        class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden w-56 -translate-x-1/2 rounded-md bg-gray-900 px-3 py-2 text-left text-xs font-normal normal-case tracking-normal text-white shadow-lg group-hover:block group-focus-within:block dark:bg-gray-700"
    >
        {{ $text }}
    </span>
</span>
