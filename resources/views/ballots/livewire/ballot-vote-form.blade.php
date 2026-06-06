<div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    @php
        $sectionCount = ($supportsBulkLotVoting && ! $votePerLot ? 1 : $ownedLotUnits->count())
            + count($proxySections)
            + count($individualSections);
    @endphp

    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
        @if ($isChangingVote)
            Change your vote
        @else
            Cast your vote
        @endif
    </h4>

    @if ($isChangingVote)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            You can update your choice until this ballot closes.
        </p>
    @endif

    <form wire:submit.prevent="submitVote" class="mt-4 space-y-5">
        @if ($supportsBulkLotVoting && ! $votePerLot)
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ $ownedLotUnits->count() }} lots
                </p>
                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                    {{ implode(', ', $ownedLotLabels) }}
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Your choice will apply to all listed lots.
                </p>

                <div class="mt-3 space-y-2">
                    @foreach ($ballot->options as $option)
                        <label class="flex items-center gap-3 rounded-md border border-gray-200 px-4 py-3 text-sm text-gray-800 dark:border-gray-600 dark:text-gray-200">
                            <input type="radio"
                                   wire:model.live="bulkSelectedOptionId"
                                   value="{{ $option->id }}"
                                   class="text-indigo-600 focus:ring-indigo-500 dark:border-gray-600">
                            <span>{{ $option->label }}</span>
                        </label>
                    @endforeach
                </div>

                @error('bulkSelectedOptionId')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @else
            @foreach ($ownedLotUnits as $unit)
                <div @class(['border-t border-gray-200 pt-4 dark:border-gray-700' => ! $loop->first || $sectionCount > $ownedLotUnits->count()])>
                    @if ($ownedLotUnits->count() > 1 || count($proxySections) > 0 || count($individualSections) > 0)
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ $ownedLotLabels[$unit->key()] }}
                        </p>
                    @endif

                    <div class="mt-2 space-y-2">
                        @foreach ($ballot->options as $option)
                            <label class="flex items-center gap-3 rounded-md border border-gray-200 px-4 py-3 text-sm text-gray-800 dark:border-gray-600 dark:text-gray-200">
                                <input type="radio"
                                       wire:model.live="selectedOptions.{{ $unit->key() }}"
                                       value="{{ $option->id }}"
                                       class="text-indigo-600 focus:ring-indigo-500 dark:border-gray-600">
                                <span>{{ $option->label }}</span>
                            </label>
                        @endforeach
                    </div>

                    @error('selectedOptions.'.$unit->key())
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        @endif

        @foreach ($proxySections as $section)
            <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ $section['label'] }}
                </p>
                <p class="mt-1 text-xs text-indigo-600 dark:text-indigo-400">Voting as proxy holder</p>

                <div class="mt-2 space-y-2">
                    @foreach ($ballot->options as $option)
                        <label class="flex items-center gap-3 rounded-md border border-gray-200 px-4 py-3 text-sm text-gray-800 dark:border-gray-600 dark:text-gray-200">
                            <input type="radio"
                                   wire:model.live="selectedOptions.{{ $section['unit']->key() }}"
                                   value="{{ $option->id }}"
                                   class="text-indigo-600 focus:ring-indigo-500 dark:border-gray-600">
                            <span>{{ $option->label }}</span>
                        </label>
                    @endforeach
                </div>

                @error('selectedOptions.'.$section['unit']->key())
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @endforeach

        @foreach ($individualSections as $section)
            <div @class(['border-t border-gray-200 pt-4 dark:border-gray-700' => $ownedLotUnits->isNotEmpty() || count($proxySections) > 0 || ! $loop->first])>
                @if ($ownedLotUnits->isNotEmpty() || count($proxySections) > 0)
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ $section['label'] }}
                    </p>
                @endif

                <div @class(['space-y-2', 'mt-2' => $ownedLotUnits->isNotEmpty() || count($proxySections) > 0])>
                    @foreach ($ballot->options as $option)
                        <label class="flex items-center gap-3 rounded-md border border-gray-200 px-4 py-3 text-sm text-gray-800 dark:border-gray-600 dark:text-gray-200">
                            <input type="radio"
                                   wire:model.live="selectedOptions.{{ $section['unit']->key() }}"
                                   value="{{ $option->id }}"
                                   class="text-indigo-600 focus:ring-indigo-500 dark:border-gray-600">
                            <span>{{ $option->label }}</span>
                        </label>
                    @endforeach
                </div>

                @error('selectedOptions.'.$section['unit']->key())
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @endforeach

        <div class="flex justify-end">

            @if ($supportsBulkLotVoting)
                    @if ($votePerLot)
                        <x-secondary-button type="button" wire:click="showBulkVote" no-spinner>
                            Vote for all lots at once
                        </x-secondary-button>
                    @else
                        <x-secondary-button type="button" wire:click="showVotePerLot" no-spinner>
                            Vote separately for each lot
                        </x-secondary-button>
                    @endif
            @endif

            <x-button type="button" class="ms-3" wire:click="submitVote" no-spinner>
                {{ $isChangingVote ? 'Update Vote' : 'Submit Vote' }}
            </x-button>
        </div>
    </form>
</div>
