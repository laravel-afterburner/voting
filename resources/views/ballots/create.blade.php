<x-app-layout title="Create Ballot">
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ isset($ballot) ? 'Edit Ballot' : 'Create Ballot' }}
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
        @livewire('voting.create', array_filter([
            'team' => $team,
            'ballotId' => isset($ballot) ? $ballot->id : null,
        ], fn ($value) => $value !== null))
    </div>
</x-app-layout>
