<div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="mb-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $ballot->status->label() }} ballot</p>
        <h3 class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $ballot->title }}</h3>
        @if ($canViewTally && $tally)
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                {{ $tally['total_votes'] }}
                total {{ ($tally['weighted'] ?? false) ? 'weighted votes' : Str::plural('vote', (int) $tally['total_votes']) }}
            </p>
        @elseif (! $canViewTally)
            <p class="mt-2 text-sm text-amber-700 dark:text-amber-300">
                Results will be available when this ballot closes.
            </p>
        @endif
    </div>

    @if ($quorum['configured'])
        <div class="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-600 dark:bg-gray-900/40">
            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Quorum</h4>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                {{ $quorum['cast'] }} of {{ $quorum['eligible'] }} eligible
                ({{ $quorum['percent'] }}%)
                @if ($quorum['met'])
                    <span class="font-medium text-green-700 dark:text-green-400">— quorum met</span>
                @else
                    <span class="font-medium text-amber-700 dark:text-amber-400">— quorum not met</span>
                @endif
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Required: {{ number_format($quorum['required'], 1) }}%
            </p>
        </div>
    @endif

    @if ($canViewTally && $tally)
        <div class="space-y-4">
            @foreach ($tally['options'] as $option)
                <div>
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $option['label'] }}</span>
                        <span class="text-gray-600 dark:text-gray-400">
                            {{ $option['count'] }}{{ ($tally['weighted'] ?? false) ? ' wgt' : '' }} ({{ $option['percentage'] }}%)
                        </span>
                    </div>
                    <div class="h-3 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                        <div class="h-full rounded-full bg-gray-800 dark:bg-gray-200"
                             style="width: {{ $option['percentage'] }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($ballot->vote_visibility->isConfidential() && $ballot->status !== \Afterburner\Voting\Enums\BallotStatus::Closed)
            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                Individual votes are hidden on confidential ballots until close. After close, only totals are shown.
            </p>
        @elseif ($ballot->vote_visibility->isConfidential())
            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                This was a confidential ballot. Individual choices are not shown — only totals.
            </p>
        @elseif ($responseDetails->isNotEmpty())
            <div class="mt-8">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Vote breakdown</h4>
                <ul class="mt-3 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($responseDetails as $detail)
                        <li class="py-2 text-sm text-gray-700 dark:text-gray-300">
                            <span class="font-medium">{{ $detail['voter_unit_label'] }}</span>
                            — {{ $detail['option_label'] }}
                            @if ($detail['via_proxy'])
                                <span class="text-xs text-gray-500 dark:text-gray-400">(via proxy, cast by {{ $detail['cast_by_name'] }})</span>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <div class="mt-4">
                    {{ $responseDetails->links() }}
                </div>
            </div>
        @endif
    @endif

    <div class="mt-6 flex flex-wrap items-center gap-3">
        @if ($canExport && $canViewTally)
            <x-secondary-button wire:click="exportResults('csv')" no-spinner>
                Export CSV
            </x-secondary-button>
            @if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class))
                <x-secondary-button wire:click="exportResults('pdf')" no-spinner>
                    Export PDF
                </x-secondary-button>
            @endif
        @endif
        <a href="{{ route('teams.ballots.show', ['team' => $team, 'ballot' => $ballot]) }}"
           wire:navigate
           class="text-sm font-medium text-gray-700 hover:text-indigo-600 dark:text-gray-300 dark:hover:text-indigo-400">
            Back to ballot
        </a>
    </div>
</div>
