<div>
    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $ballot->type->label() }}</p>
                <h3 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $ballot->title }}</h3>
                @if ($ballot->description)
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">{{ $ballot->description }}</p>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span @class([
                    'rounded-full px-3 py-1 text-xs font-medium',
                    $ballot->status->badgeClasses(),
                ])>
                    {{ $ballot->status->label() }}
                </span>

                @if ($canUpdate)
                    <x-button href="{{ route('teams.ballots.edit', ['team' => $team, 'ballot' => $ballot]) }}" wire:navigate>
                        Edit ballot
                    </x-button>
                @endif
            </div>
        </div>

        <dl class="mt-6 grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Electorate</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $ballot->electorate->label() }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Visibility</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $ballot->vote_visibility->label() }}</dd>
            </div>
            @if ($ballot->opens_at)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Opens</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{!! \Afterburner\Voting\Support\TeamDateTime::formatDisplay($team, $ballot->opens_at) !!}</dd>
                </div>
            @endif
            @if ($ballot->closes_at)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Closes</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{!! \Afterburner\Voting\Support\TeamDateTime::formatDisplay($team, $ballot->closes_at) !!}</dd>
                </div>
            @endif
        </dl>

        @if ($responses->isNotEmpty())
            <div class="-mx-6 mt-5 border-y border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/30">
                @foreach ($responses as $response)
                    <div @class([
                        'flex flex-wrap items-center justify-between gap-3',
                        'mt-3' => ! $loop->first,
                    ])>
                        <div class="min-w-0">
                            @if ($loop->first)
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Your vote</p>
                            @endif
                            <p @class([
                                'flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-900 dark:text-gray-100',
                                'mt-1' => $loop->first,
                            ])>
                                <span class="inline-flex items-center gap-1.5 font-medium text-green-700 dark:text-green-400">
                                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    {{ $response->option->label }}
                                </span>
                                <span class="text-gray-500 dark:text-gray-400">cast {{ $response->cast_at->diffForHumans() }}</span>
                            </p>
                        </div>
                        @if ($allowVoteRevocation && $ballot->isOpen() && auth()->user()->can('revokeVote', [$ballot, $response->voter_unit_type, $response->voter_unit_id]))
                            <x-action-icon
                                type="delete"
                                wire:click="revokeVote({{ $response->id }})"
                                wire:confirm="Revoke this vote? You will not be able to vote again on this ballot for this unit."
                                class="shrink-0"
                                title="Revoke vote"
                            />
                        @endif
                    </div>
                @endforeach
                @if ($ballot->isOpen() && $ballot->closes_at && ! $allowVoteRevocation)
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        You may change your vote until {{ \Afterburner\Voting\Support\TeamDateTime::format($team, $ballot->closes_at) }}.
                    </p>
                @endif
                @if ($allowVoteRevocation && $ballot->isOpen())
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        Revoking withdraws your vote for this unit; you cannot cast again on this ballot.
                    </p>
                @endif
            </div>
        @endif

        <div class="mt-6 flex flex-wrap justify-end gap-3">
            @if ($canDelete)
                <x-danger-button type="button" wire:click="confirmBallotDeletion" no-spinner>
                    Delete Ballot
                </x-danger-button>
            @endif
            @if ($canPublish)
                <x-button wire:click="publishBallot" no-spinner>
                    Publish Ballot
                </x-button>
            @endif
            @if ($canClose)
                <x-secondary-button wire:click="closeBallot" no-spinner>
                    Close Ballot
                </x-secondary-button>
            @endif
            @if ($canViewResults)
                <x-secondary-button href="{{ route('teams.ballots.results', ['team' => $team, 'ballot' => $ballot]) }}" wire:navigate>
                    View Results
                </x-secondary-button>
            @endif
        </div>
    </div>

    @if (\Afterburner\Voting\Support\DocumentsIntegration::isEnabled())
        @livewire('voting.ballot-documents', [
            'teamId' => $team->id,
            'ballotId' => $ballot->id,
        ], key('ballot-documents-'.$ballot->id))
    @elseif (\Afterburner\Voting\Support\DocumentsIntegration::shouldPromptInstall())
        @include('afterburner-voting::components.documents-install-prompt', ['context' => 'ballot'])
    @endif

    @if ($canVote)
        <div class="mt-6 space-y-6">
            @foreach ($eligibleUnits as $unit)
                @livewire('voting.vote-form', [
                    'ballotId' => $ballot->id,
                    'voterUnitType' => $unit->type,
                    'voterUnitId' => $unit->id,
                ], key('vote-form-'.$unit->key()))
            @endforeach
        </div>
    @endif

    <x-confirmation-modal wire:model.live="confirmingBallotDeletion">
        <x-slot name="title">
            Delete Ballot
        </x-slot>

        <x-slot name="content">
            Are you sure you want to delete this ballot? This action cannot be undone.
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="cancelBallotDeletion">
                Cancel
            </x-secondary-button>
            <x-danger-button wire:click="deleteBallot" class="ms-3" no-spinner>
                Delete
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>
</div>
