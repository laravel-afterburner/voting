@php($wrapperClass = $class ?? 'mt-6')

<div class="{{ $wrapperClass }} rounded-lg border border-dashed border-gray-300 bg-gray-50 p-5 dark:border-gray-600 dark:bg-gray-900/40">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Want to attach documents to this {{ $context }}?
        Install the <span class="font-medium text-gray-600 dark:text-gray-300">Afterburner Documents</span> package.
    </p>
</div>
