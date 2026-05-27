<div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <form wire:submit.prevent="saveDraft" class="space-y-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Title</label>
            <input type="text" wire:model="title"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 sm:text-sm">
            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
            <textarea wire:model="description" rows="4"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 sm:text-sm"></textarea>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                <select wire:model="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 sm:text-sm">
                    <option value="poll">Poll</option>
                    <option value="resolution">Resolution</option>
                    <option value="election">Election</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Electorate</label>
                <select wire:model="electorate" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 sm:text-sm">
                    <option value="all_members">All Members</option>
                    <option value="council">Council</option>
                    <option value="custom">Custom</option>
                </select>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Opens At <span class="text-red-600">*</span>
                    <span class="font-normal text-gray-500 dark:text-gray-400">({{ $scheduleTimezone }}, required to publish)</span>
                </label>
                <input type="datetime-local" wire:model="opensAt"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 sm:text-sm">
                @error('opensAt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Closes At <span class="text-red-600">*</span>
                    <span class="font-normal text-gray-500 dark:text-gray-400">({{ $scheduleTimezone }}, required to publish)</span>
                </label>
                <input type="datetime-local" wire:model="closesAt"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 sm:text-sm">
                @error('closesAt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Quorum Percent (optional)</label>
            <input type="number" step="0.01" min="0" max="100" wire:model="quorumPercent"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 sm:text-sm">
        </div>

        <div>
            <div class="flex items-center justify-between">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Options</label>
                <button type="button" wire:click="addOption" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
                    Add option
                </button>
            </div>
            <div class="mt-3 space-y-3">
                @foreach ($options as $index => $option)
                    <div class="flex items-start gap-3">
                        <input type="text"
                               wire:model="options.{{ $index }}.label"
                               placeholder="Option label"
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 sm:text-sm">
                        @if (count($options) > 2)
                            <button
                                type="button"
                                wire:click="removeOption({{ $index }})"
                                wire:loading.attr="disabled"
                                class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded shrink-0"
                                title="Remove option"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-3">
            <x-secondary-button type="submit">
                Save Draft
            </x-secondary-button>
            <x-button type="button" wire:click="saveAndPublish" no-spinner>
                Save & Publish
            </x-button>
        </div>
    </form>

    @if (\Afterburner\Voting\Support\DocumentsIntegration::isEnabled())
        <div class="mt-6">
            @if ($ballotId)
                @livewire('voting.ballot-documents', [
                    'teamId' => $team->id,
                    'ballotId' => $ballotId,
                ], key('ballot-documents-create-'.$ballotId))
            @else
                <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-5 dark:border-gray-600 dark:bg-gray-900/40">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Save a draft first to attach supporting documents to this ballot.
                    </p>
                </div>
            @endif
        </div>
    @endif
</div>
