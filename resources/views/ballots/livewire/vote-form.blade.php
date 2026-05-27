<div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
        {{ $isChangingVote ? 'Change your vote for' : 'Cast vote for' }} {{ $unitLabel }}
    </h4>
    @if ($isProxyVote ?? false)
        <p class="mt-1 text-xs text-indigo-600 dark:text-indigo-400">Voting as proxy holder</p>
    @endif
    @if ($isChangingVote)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            You can update your choice until this ballot closes.
        </p>
    @endif

    <form wire:submit.prevent="submitVote" class="mt-4 space-y-4">
        <div class="space-y-2">
            @foreach ($ballot->options as $option)
                <label class="flex items-center gap-3 rounded-md border border-gray-200 px-4 py-3 text-sm text-gray-800 dark:border-gray-600 dark:text-gray-200">
                    <input type="radio"
                           wire:model.live="selectedOptionId"
                           value="{{ $option->id }}"
                           class="text-indigo-600 focus:ring-indigo-500 dark:border-gray-600">
                    <span>{{ $option->label }}</span>
                </label>
            @endforeach
        </div>

        @error('selectedOptionId')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror

        <div class="flex justify-end">
            <x-button type="button" wire:click="submitVote" no-spinner>
                {{ $isChangingVote ? 'Update Vote' : 'Submit Vote' }}
            </x-button>
        </div>
    </form>
</div>
