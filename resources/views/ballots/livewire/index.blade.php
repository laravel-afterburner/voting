<div>
    @if ($actionRequiredCount > 0)
        <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200">
            Action required: you have {{ $actionRequiredCount }} open {{ Str::plural('ballot', $actionRequiredCount) }} waiting for your vote.
        </div>
    @endif

    @if ($canCreate)
        <div class="mb-6 flex items-center justify-end">
            <x-button wire:click="createBallot" no-spinner>
                Create Ballot
            </x-button>
        </div>
    @endif

    <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-8">
            @foreach (['open' => 'Open', 'upcoming' => 'Upcoming', 'closed' => 'Closed'] as $key => $label)
                <button wire:click="setTab('{{ $key }}')"
                        type="button"
                        class="whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium {{ $tab === $key ? 'border-gray-800 text-gray-900 dark:border-gray-200 dark:text-gray-100' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-300' }}">
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    <div class="space-y-4">
        @forelse ($ballots as $ballot)
            <a href="{{ route('teams.ballots.show', ['team' => $team, 'ballot' => $ballot]) }}"
               wire:navigate
               class="block rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-500">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $ballot->title }}</h3>
                        @if ($ballot->description)
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ Str::limit($ballot->description, 140) }}</p>
                        @endif
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ $ballot->type->label() }} · {{ $ballot->status->label() }}
                            @if ($ballot->published_at)
                                · Published {{ $ballot->published_at->diffForHumans() }}
                            @endif
                        </p>
                    </div>
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                        {{ $ballot->status->label() }}
                    </span>
                </div>
            </a>
        @empty
            <div class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                No ballots in this tab yet.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $ballots->links() }}
    </div>
</div>
