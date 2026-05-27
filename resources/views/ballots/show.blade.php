<x-app-layout :title="'Ballot - '.$ballot->title">
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            Ballot - {{ $ballot->title }}
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
        @livewire('voting.show', ['team' => $team, 'ballot' => $ballot])
    </div>
</x-app-layout>
