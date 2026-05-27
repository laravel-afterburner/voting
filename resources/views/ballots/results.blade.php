<x-app-layout :title="$ballot->title.' Results'">
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            Results: {{ $ballot->title }}
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
        @livewire('voting.results', ['team' => $team, 'ballot' => $ballot])
    </div>
</x-app-layout>
