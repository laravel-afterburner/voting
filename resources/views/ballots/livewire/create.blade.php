<div>
    <x-form-section submit="saveDraft">
        <x-slot name="title">
            @if ($isEditing)
                Ballot details
            @else
                Create ballot
            @endif
        </x-slot>

        <x-slot name="description">
            @if ($isEditing)
                Update this ballot's title, options, schedule, and electorate. Vote visibility cannot be changed after publish.
            @else
                Set up a new poll, resolution, or election. Save as a draft to attach documents, or publish when the schedule and options are ready.
            @endif
        </x-slot>

        <x-slot name="form">
            <div class="col-span-6 space-y-6">
                <div>
                    <x-label for="title" value="Title *" />
                    <x-input id="title" type="text" class="mt-1 block w-full" wire:model="title" />
                    <x-input-error for="title" class="mt-2" />
                </div>

                <div>
                    <x-label for="description" value="Description" />
                    <x-textarea-input id="description" wire:model="description" rows="4" class="mt-1 block w-full" />
                    <x-input-error for="description" class="mt-2" />
                </div>

                <div class="flex flex-wrap items-start gap-4">
                    <div class="w-44">
                        <x-label for="type" value="Type" />
                        <x-select-input id="type" wire:model="type" class="mt-1 block w-full">
                            <option value="poll">Poll</option>
                            <option value="resolution">Resolution</option>
                            <option value="election">Election</option>
                        </x-select-input>
                    </div>
                    <div class="min-w-52 flex-1">
                        <x-label for="electorate" value="Electorate" />
                        <x-afterburner-voting::electorate-select
                            id="electorate"
                            :options="$electorateOptions"
                            :selected="$electorate"
                        />
                        <x-input-error for="electorate" class="mt-2" />
                        <x-input-error for="electorate.*" class="mt-2" />
                    </div>
                    <div class="w-28 overflow-visible">
                        <div class="flex items-center gap-1.5">
                            <x-label for="quorumPercent" value="Quorum %" />
                            <x-afterburner-voting::info-hint
                                label="Quorum requirement"
                                text="A quorum is the minimum percentage of eligible voters who must participate before the vote is valid. Leave empty for no minimum."
                                align="end"
                            />
                        </div>
                        <x-input id="quorumPercent" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" wire:model="quorumPercent" />
                        <x-input-error for="quorumPercent" class="mt-2" />
                    </div>
                </div>

                <div class="flex flex-wrap gap-4">
                    <div class="w-full max-w-xs">
                        <x-label for="opensAt" value="Opens at ({{ $scheduleTimezoneLabel }}) *" />
                        <p class="text-xs text-gray-500 dark:text-gray-400">(required to publish)</p>
                        <x-input id="opensAt" type="datetime-local" class="mt-1 block w-full" wire:model="opensAt" />
                        <x-input-error for="opensAt" class="mt-2" />
                    </div>
                    <div class="w-full max-w-xs">
                        <x-label for="closesAt" value="Closes at ({{ $scheduleTimezoneLabel }}) *" />
                        <p class="text-xs text-gray-500 dark:text-gray-400">(required to publish)</p>
                        <x-input id="closesAt" type="datetime-local" class="mt-1 block w-full" wire:model="closesAt" />
                        <x-input-error for="closesAt" class="mt-2" />
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <x-label value="Options" />
                        <button type="button" wire:click="addOption" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
                            Add option
                        </button>
                    </div>
                    <div class="mt-3 space-y-3">
                        @foreach ($options as $index => $option)
                            <div class="flex items-start gap-3">
                                <x-input
                                    type="text"
                                    wire:model="options.{{ $index }}.label"
                                    placeholder="Option label"
                                    class="block w-full"
                                />
                                @if (count($options) > 2)
                                    <x-action-icon
                                        type="delete"
                                        wire:click="removeOption({{ $index }})"
                                        wire:loading.attr="disabled"
                                        class="shrink-0"
                                        title="Remove option"
                                    />
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div @class(['flex items-start gap-3', 'opacity-60' => $voteVisibilityLocked])>
                    <input
                        id="confidentialVoting"
                        type="checkbox"
                        wire:model.live="confidentialVoting"
                        @disabled($voteVisibilityLocked)
                        class="mt-1 rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-900 disabled:cursor-not-allowed"
                    />
                    <div class="min-w-0">
                        <div class="inline-flex flex-wrap items-center gap-1.5">
                            <label
                                for="confidentialVoting"
                                @class([
                                    'text-sm font-medium text-gray-900 dark:text-gray-100',
                                    'cursor-pointer' => ! $voteVisibilityLocked,
                                ])
                            >
                                Confidential voting
                            </label>
                            <x-afterburner-voting::info-hint
                                label="Confidential voting"
                                text="When enabled, individual choices are never shown in results — only vote totals after the ballot closes. Your own vote is still visible to you on the ballot page."
                            />
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Hide who voted for which option; results show counts only.
                        </p>
                        @if ($voteVisibilityLocked)
                            <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                                Locked after publish — vote visibility cannot be changed, so confidential votes cannot be exposed later.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </x-slot>

        <x-slot name="actions">
            <x-action-message class="me-3" on="saved" />
            <x-secondary-button type="submit" wire:loading.attr="disabled" wire:target="saveDraft">
                Save draft
            </x-secondary-button>
            <x-button type="button" class="ms-3" wire:click="saveAndPublish" wire:loading.attr="disabled" wire:target="saveAndPublish" no-spinner>
                Publish
            </x-button>
        </x-slot>
    </x-form-section>

    @if (\Afterburner\Voting\Support\DocumentsIntegration::isEnabled())
        <x-section-border />

        <x-action-section>
            <x-slot name="title">
                Supporting documents
            </x-slot>

            <x-slot name="description">
                Attach reference materials voters can review before casting a ballot.
            </x-slot>

            <x-slot name="content">
                @if ($ballotId)
                    @livewire('voting.ballot-documents', [
                        'teamId' => $team->id,
                        'ballotId' => $ballotId,
                        'embedded' => true,
                    ], key('ballot-documents-create-'.$ballotId))
                @else
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Save a draft first to attach supporting documents to this ballot.
                    </p>
                @endif
            </x-slot>
        </x-action-section>
    @elseif (\Afterburner\Voting\Support\DocumentsIntegration::shouldPromptInstall())
        <x-section-border />

        @include('afterburner-voting::components.documents-install-prompt', ['context' => 'ballot'])
    @endif
</div>
