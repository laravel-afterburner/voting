<div class="space-y-8">
    @unless ($proxiesEnabled)
        <div class="rounded-md bg-yellow-50 dark:bg-yellow-900/20 p-4">
            <p class="text-sm text-yellow-800 dark:text-yellow-200">
                Proxy votes are not enabled for this {{ entity_label() }}.
            </p>
        </div>
    @endunless

    @if ($proxiesEnabled)
        <x-form-section submit="grantProxy">
            <x-slot name="title">
                Grant proxy
            </x-slot>

            <x-slot name="description">
                Authorize a {{ entity_label() }} member to vote on your behalf for a {{ strtolower($voterUnitSelectionLabel) }}. A proxy applies to one ballot at a time, or to all open ballots for that {{ strtolower($voterUnitSelectionLabel) }}.
            </x-slot>

            <x-slot name="form">
                <div class="col-span-6 sm:col-span-4">
                    <x-label for="voterUnitId" :value="$voterUnitSelectionLabel" />
                    <select
                        id="voterUnitId"
                        wire:model.live="voterUnitId"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                    >
                        <option value="">Select a {{ strtolower($voterUnitSelectionLabel) }}</option>
                        @foreach ($grantableUnits as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->label }}</option>
                        @endforeach
                    </select>
                    <x-input-error for="voterUnitId" class="mt-2" />
                </div>

                <div class="col-span-6 sm:col-span-4">
                    <x-label for="proxyHolderUserId" value="Proxy holder" />
                    <select
                        id="proxyHolderUserId"
                        wire:model="proxyHolderUserId"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                    >
                        <option value="">Select a {{ entity_label() }} member</option>
                        @foreach ($teamMembers as $member)
                            <option value="{{ $member->id }}">{{ $member->name }} ({{ $member->email }})</option>
                        @endforeach
                    </select>
                    <x-input-error for="proxyHolderUserId" class="mt-2" />
                </div>

                <div class="col-span-6 sm:col-span-4">
                    <x-label value="Ballot scope" />
                    <div class="mt-2 space-y-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="radio" wire:model.live="ballotScope" value="single" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900">
                            Single open ballot
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="radio" wire:model.live="ballotScope" value="all_open" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900">
                            All open ballots for this {{ strtolower($voterUnitSelectionLabel) }}
                        </label>
                    </div>
                </div>

                @if ($ballotScope === 'single')
                    <div class="col-span-6 sm:col-span-4">
                        <x-label for="ballotId" value="Ballot" />
                        <select
                            id="ballotId"
                            wire:model="ballotId"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                            @disabled($eligibleBallots->isEmpty())
                        >
                            <option value="">
                                @if ($voterUnitId && $eligibleBallots->isEmpty())
                                    No eligible open ballots
                                @else
                                    Select a ballot
                                @endif
                            </option>
                            @foreach ($eligibleBallots as $ballot)
                                <option value="{{ $ballot->id }}">{{ $ballot->title }}</option>
                            @endforeach
                        </select>
                        <x-input-error for="ballotId" class="mt-2" />
                    </div>
                @endif

                <div class="col-span-6 sm:col-span-4">
                    <x-label for="validUntil" value="Valid until (optional)" />
                    <x-input id="validUntil" type="datetime-local" class="mt-1 block w-full" wire:model="validUntil" />
                    <x-input-error for="validUntil" class="mt-2" />
                </div>
            </x-slot>

            <x-slot name="actions">
                <x-button type="submit" wire:loading.attr="disabled" no-spinner>
                    Grant proxy
                </x-button>
            </x-slot>
        </x-form-section>

        <x-section-border />
    @endif

    <x-action-section>
        <x-slot name="title">
            @if ($canManageAll)
                All proxy votes
            @else
                Your proxy votes
            @endif
        </x-slot>

        <x-slot name="description">
            Active and revoked proxies for {{ strtolower($voterUnitSelectionLabel) }}s on this {{ entity_label() }}.
        </x-slot>

        <x-slot name="content">
            @if ($proxies->isEmpty())
                <p class="text-sm text-gray-600 dark:text-gray-400">No proxy votes recorded.</p>
            @else
                <ul class="divide-y divide-gray-200 dark:divide-gray-700 overflow-hidden bg-white dark:bg-gray-800 shadow-sm outline outline-1 outline-gray-900/5 dark:outline-gray-700/50 sm:rounded-xl">
                    @foreach ($proxies as $proxy)
                        @php
                            $canRevoke = auth()->user()?->can('revoke', $proxy);
                        @endphp
                        <li class="px-4 py-4 sm:px-6" wire:key="proxy-{{ $proxy->id }}">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $unitLabelsById->get($proxy->grantor_voter_unit_id, $proxy->grantor_voter_unit_id) }}
                                        · {{ $proxy->ballot?->title ?? 'Ballot' }}
                                    </p>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        Holder: {{ $proxy->proxyHolder?->name ?? 'Unknown' }}
                                        · Granted by {{ $proxy->grantedBy?->name ?? 'Unknown' }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        @if ($proxy->revoked_at)
                                            Revoked {{ $proxy->revoked_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                        @elseif ($proxy->isActive())
                                            Active
                                            @if ($proxy->valid_until)
                                                · until {{ $proxy->valid_until->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                            @endif
                                        @else
                                            Expired
                                        @endif
                                    </p>
                                </div>

                                @if ($canRevoke && $proxy->isActive())
                                    <x-danger-button type="button" wire:click="revokeProxy({{ $proxy->id }})" wire:confirm="Revoke this proxy?" no-spinner>
                                        Revoke
                                    </x-danger-button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-4">
                    {{ $proxies->links() }}
                </div>
            @endif
        </x-slot>
    </x-action-section>
</div>
