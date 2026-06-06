<div>
    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $ballot->type->label() }}</p>
                    <span @class([
                        'rounded-full px-3 py-1 text-xs font-medium',
                        $ballot->status->badgeClasses(),
                    ])>
                        {{ $ballot->status->label() }}
                    </span>
                </div>
                <h3 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $ballot->title }}</h3>
                @if ($ballot->description)
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">{{ $ballot->description }}</p>
                @endif
            </div>

            @if ($canUpdate)
                <x-button class="shrink-0" href="{{ route('teams.ballots.edit', ['team' => $team, 'ballot' => $ballot]) }}" wire:navigate>
                    Edit
                </x-button>
            @endif
        </div>

        <dl class="mt-6 grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Electorate</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $ballot->electorate->label() }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Vote visibility</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $ballot->vote_visibility->label() }}</dd>
            </div>
            @if ($ballot->opens_at)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Opens</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {!! \App\Support\TeamDateTime::formatDisplay($team, $ballot->opens_at) !!}
                    </dd>
                </div>
            @endif
            @if ($ballot->closes_at)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Closes</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {!! \App\Support\TeamDateTime::formatDisplay($team, $ballot->closes_at) !!}
                    </dd>
                </div>
            @endif
        </dl>

        @if ($showSupportingDocuments)
            <div class="-mx-6 mt-5 border-y border-gray-200 px-6 py-4 dark:border-gray-700">
                @livewire('voting.ballot-documents', [
                    'teamId' => $team->id,
                    'ballotId' => $ballot->id,
                    'inPanel' => true,
                ], key('ballot-documents-'.$ballot->id))
            </div>
        @elseif (\Afterburner\Voting\Support\DocumentsIntegration::shouldPromptInstall() && $ballot->isOpen())
            <div class="-mx-6 mt-5 border-y border-gray-200 px-6 py-4 dark:border-gray-700">
                @include('afterburner-voting::components.documents-install-prompt', ['context' => 'ballot', 'class' => ''])
            </div>
        @endif

        @if ($voteSummaries->isNotEmpty())
            <div wire:key="vote-summary-{{ $voteSummaryVersion }}" @class([
                '-mx-6 mt-5 border-y border-gray-200 px-6 py-4 dark:border-gray-700',
                config('afterburner-voting.ui.vote_cast_panel_classes', 'bg-gray-50 dark:bg-gray-900/30'),
            ])>
                @foreach ($voteSummaries as $summary)
                    <div @class([
                        'flex flex-wrap items-center justify-between gap-3',
                        'mt-3' => ! $loop->first,
                    ])>
                        <div class="min-w-0 flex-1">
                            @if ($loop->first)
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Your vote</p>
                                    @if ($canVote && ! $showVoteForm)
                                        <x-secondary-button type="button" wire:click="showUpdateVoteForm" no-spinner>
                                            Update vote
                                        </x-secondary-button>
                                    @endif
                                </div>
                            @endif
                            <p @class([
                                'flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-900 dark:text-gray-100',
                                'mt-1' => $loop->first,
                            ])>
                                @if ($summary['unit_label'])
                                    <span class="text-gray-500 dark:text-gray-400">{{ $summary['unit_label'] }}: </span>
                                @endif
                                <span class="inline-flex items-center gap-1.5 font-medium text-green-700 dark:text-green-400">
                                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    {{ $summary['option_label'] }}
                                </span>
                                @if ($summary['via_proxy'])
                                    <span class="text-xs text-indigo-600 dark:text-indigo-400">via proxy</span>
                                @endif
                            </p>
                        </div>
                        @if ($allowVoteRevocation && $ballot->isOpen())
                            @php
                                $canRevokeSummary = collect($summary['voter_units'])->every(
                                    fn (array $unit) => auth()->user()->can('revokeVote', [$ballot, $unit['type'], $unit['id']])
                                );
                            @endphp
                            @if ($canRevokeSummary)
                                <x-action-icon
                                    type="delete"
                                    wire:click="revokeVotes(@js($summary['response_ids']))"
                                    wire:confirm="{{ $summary['consolidated'] ? 'Revoke these votes? You will not be able to vote again on this ballot for these lots.' : 'Revoke this vote? You will not be able to vote again on this ballot for this unit.' }}"
                                    class="shrink-0"
                                    title="Revoke vote"
                                />
                            @endif
                        @endif
                    </div>
                @endforeach
                @if ($ballot->isOpen() && $ballot->closes_at && ! $allowVoteRevocation)
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        You may change your vote until {!! \App\Support\TeamDateTime::formatDisplay($team, $ballot->closes_at) !!}.
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
                    Delete
                </x-danger-button>
            @endif
            @if ($canPublish)
                <x-button wire:click="publishBallot" no-spinner>
                    Publish
                </x-button>
            @endif
            @if ($canReopen)
                <x-button wire:click="reopenBallot" no-spinner>
                    Reopen
                </x-button>
            @endif
            @if ($canClose)
                <x-secondary-button wire:click="closeBallot" no-spinner>
                    Close
                </x-secondary-button>
            @endif
            @if ($canViewResults)
                <x-secondary-button href="{{ route('teams.ballots.results', ['team' => $team, 'ballot' => $ballot]) }}" wire:navigate>
                    View Results
                </x-secondary-button>
            @endif
        </div>
    </div>

    @if ($canVote && $showVoteForm)
        <div class="mt-6">
            @livewire('voting.ballot-vote-form', [
                'ballotId' => $ballot->id,
                'votePerLot' => $votePerLot,
            ], key('ballot-vote-form-'.$ballot->id.'-'.$voteSummaryVersion))
        </div>
    @endif

    <x-confirmation-modal wire:model.live="confirmingBallotDeletion">
        <x-slot name="title">
            Delete Ballot
        </x-slot>

        <x-slot name="content">
            @if ($hasRecordedVotes)
                <div class="rounded-md border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950/40">
                    <p class="text-sm font-semibold text-red-800 dark:text-red-200">
                        This ballot has recorded votes.
                    </p>
                    <p class="mt-2 text-sm text-red-700 dark:text-red-300">
                        Deleting it will permanently remove the ballot and all {{ number_format($ballot->responses_count) }}
                        {{ Str::plural('vote', $ballot->responses_count) }} cast on it. This cannot be undone and may affect audit records or published results.
                    </p>
                </div>
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                    Are you sure you want to delete <span class="font-medium text-gray-900 dark:text-gray-100">{{ $ballot->title }}</span>?
                </p>
            @else
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Are you sure you want to delete <span class="font-medium text-gray-900 dark:text-gray-100">{{ $ballot->title }}</span>? This action cannot be undone.
                </p>
            @endif
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
