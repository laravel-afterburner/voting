<div>
    @if ($actionRequiredCount > 0)
        <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200">
            Action required: you have {{ $actionRequiredCount }} open {{ Str::plural('ballot', $actionRequiredCount) }} waiting for your vote.
        </div>
    @endif

    @if ($canCreate)
        <x-page-actions>
            <x-button href="{{ route('teams.ballots.create', ['team' => $team]) }}" wire:navigate>
                Create ballot
            </x-button>
        </x-page-actions>
    @endif

    <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
        <x-responsive-table :bleed="false">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th scope="col" class="text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Ballot
                    </th>
                    <th scope="col" class="table-cell-md text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Type
                    </th>
                    <th scope="col" class="text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Status
                    </th>
                    <th scope="col" class="table-cell-lg text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Opens
                    </th>
                    <th scope="col" class="table-cell-lg text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Closes
                    </th>
                    <th scope="col" class="relative">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                @forelse ($ballots as $ballot)
                    <tr class="group hover:bg-gray-50 dark:hover:bg-gray-700/50" wire:key="ballot-row-{{ $ballot->id }}">
                        <td>
                            <a
                                href="{{ route('teams.ballots.show', ['team' => $team, 'ballot' => $ballot]) }}"
                                wire:navigate
                                class="text-left text-sm font-medium text-gray-900 hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400"
                            >
                                {{ $ballot->title }}
                            </a>
                            @if ($ballot->description)
                                <p class="mt-1 max-w-md truncate text-xs text-gray-500 dark:text-gray-400">
                                    {{ $ballot->description }}
                                </p>
                            @endif
                        </td>
                        <td class="table-cell-md whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                            {{ $ballot->type->label() }}
                        </td>
                        <td class="whitespace-nowrap text-sm">
                            <span @class([
                                'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                                $ballot->status->listPhaseBadgeClasses(),
                            ])>
                                {{ $ballot->status->listPhaseLabel() }}
                            </span>
                        </td>
                        <td class="table-cell-lg whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                            @if ($ballot->opens_at)
                                {!! \Afterburner\Voting\Support\TeamDateTime::formatDisplay($team, $ballot->opens_at) !!}
                            @else
                                —
                            @endif
                        </td>
                        <td class="table-cell-lg whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                            @if ($ballot->closes_at)
                                {!! \Afterburner\Voting\Support\TeamDateTime::formatDisplay($team, $ballot->closes_at) !!}
                            @else
                                —
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end space-x-2">
                                <x-action-icon type="view" href="{{ route('teams.ballots.show', ['team' => $team, 'ballot' => $ballot]) }}" wire:navigate title="View ballot" />
                                @can('update', $ballot)
                                    <x-action-icon type="edit" href="{{ route('teams.ballots.edit', ['team' => $team, 'ballot' => $ballot]) }}" wire:navigate title="Edit ballot" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            No ballots yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-responsive-table>
    </div>

    <div class="mt-6">
        {{ $ballots->links() }}
    </div>
</div>
