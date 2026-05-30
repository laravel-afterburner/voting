<div>
    @if ($actionRequiredCount > 0)
        <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200">
            Action required: you have {{ $actionRequiredCount }} open {{ Str::plural('ballot', $actionRequiredCount) }} waiting for your vote.
        </div>
    @endif

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="w-full sm:w-auto sm:min-w-[12rem]">
            <x-label for="ballotTab" value="Show" />
            <x-select-input id="ballotTab" wire:model.live="tab" class="mt-1 block w-full">
                <option value="open">Open</option>
                <option value="upcoming">Upcoming</option>
                <option value="closed">Closed</option>
            </x-select-input>
        </div>

        @if ($canCreate)
            <x-button wire:click="createBallot" no-spinner>
                Create ballot
            </x-button>
        @endif
    </div>

    <div class="overflow-hidden bg-white shadow sm:rounded-lg dark:bg-gray-800">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Ballot
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Type
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Status
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Schedule
                    </th>
                    <th scope="col" class="relative px-6 py-3">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                @forelse ($ballots as $ballot)
                    <tr class="group hover:bg-gray-50 dark:hover:bg-gray-700/50" wire:key="ballot-row-{{ $ballot->id }}">
                        <td class="px-6 py-4">
                            <button
                                type="button"
                                wire:click="viewBallot({{ $ballot->id }})"
                                class="text-left text-sm font-medium text-gray-900 hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400"
                            >
                                {{ $ballot->title }}
                            </button>
                            @if ($ballot->description)
                                <p class="mt-1 max-w-md truncate text-xs text-gray-500 dark:text-gray-400">
                                    {{ $ballot->description }}
                                </p>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                            {{ $ballot->type->label() }}
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm">
                            <span @class([
                                'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                                $ballot->status->badgeClasses(),
                            ])>
                                {{ $ballot->status->label() }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                            @if ($ballot->opens_at || $ballot->closes_at)
                                <div class="space-y-1">
                                    @if ($ballot->opens_at)
                                        <div>
                                            <span class="text-xs text-gray-400 dark:text-gray-500">Opens</span>
                                            <div>{!! \Afterburner\Voting\Support\TeamDateTime::formatDisplay($team, $ballot->opens_at) !!}</div>
                                        </div>
                                    @endif
                                    @if ($ballot->closes_at)
                                        <div>
                                            <span class="text-xs text-gray-400 dark:text-gray-500">Closes</span>
                                            <div>{!! \Afterburner\Voting\Support\TeamDateTime::formatDisplay($team, $ballot->closes_at) !!}</div>
                                        </div>
                                    @endif
                                </div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                            <div class="flex items-center justify-end space-x-2">
                                <x-action-icon type="view" wire:click="viewBallot({{ $ballot->id }})" title="View ballot" />
                                @can('update', $ballot)
                                    <x-action-icon type="edit" wire:click="editBallot({{ $ballot->id }})" title="Edit ballot" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            No ballots in this list yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $ballots->links() }}
    </div>
</div>
