<div>
    <x-form-section>
        <x-slot name="title">
            Voting
        </x-slot>

        <x-slot name="description">
            Configure default ballot options for this {{ config('afterburner.entity_label', 'team') }}. New ballots inherit these values; individual ballots can override them.
        </x-slot>

        <x-slot name="form">
            <div class="col-span-6 space-y-6">
                <div>
                    <div class="flex items-center gap-1.5">
                        <x-label for="defaultQuorumPercent" value="Default quorum (%)" />
                        <x-afterburner-voting::info-hint
                            label="Quorum requirement"
                            text="A quorum is the minimum percentage of eligible voters who must participate before the vote is valid. Leave empty for no minimum."
                        />
                    </div>
                    <x-input
                        id="defaultQuorumPercent"
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        class="mt-1 block w-full max-w-xs"
                        wire:model="defaultQuorumPercent"
                        placeholder="No quorum requirement"
                    />
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Leave empty for no quorum requirement on new ballots.
                    </p>
                    <x-input-error for="defaultQuorumPercent" class="mt-2" />
                </div>

                <div>
                    <div class="flex items-center gap-1.5">
                        <x-label for="defaultVoteVisibility" value="Default vote visibility" />
                        <x-afterburner-voting::info-hint
                            label="Default vote visibility"
                            :scrollable="true"
                        >
                            @foreach ($visibilityOptions as $option)
                                <p>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $option->label() }}:</span>
                                    {{ $option->description() }}
                                </p>
                            @endforeach
                        </x-afterburner-voting::info-hint>
                    </div>
                    <select
                        id="defaultVoteVisibility"
                        wire:model="defaultVoteVisibility"
                        class="mt-1 block w-full max-w-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                    >
                        @foreach ($visibilityOptions as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        New ballots inherit this unless you change confidential voting on the ballot form.
                    </p>
                    <x-input-error for="defaultVoteVisibility" class="mt-2" />
                </div>

                <label class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        wire:model="allowProxyVotes"
                        class="mt-1 rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-900"
                    />
                    <span>
                        <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">
                            Allow proxy votes
                        </span>
                        <span class="block mt-1 text-sm text-gray-500 dark:text-gray-400">
                            When enabled, members can grant proxies so another user may cast on their behalf.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        wire:model="lockDesignationDuringOpenBallots"
                        class="mt-1 rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-900"
                    />
                    <span>
                        <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">
                            Lock designated voter changes during open ballots
                        </span>
                        <span class="block mt-1 text-sm text-gray-500 dark:text-gray-400">
                            When enabled, the host application should prevent changing designated voters while any ballot is open. This package stores the preference only; enforcement is handled by the host app.
                        </span>
                    </span>
                </label>
            </div>
        </x-slot>

        <x-slot name="actions">
            <x-action-message class="me-3" on="saved">
                Saved.
            </x-action-message>

            <x-button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" no-spinner>
                Save
            </x-button>
        </x-slot>
    </x-form-section>
</div>
