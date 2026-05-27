<x-app-layout title="Voting Settings">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Voting Settings
        </h2>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            @if (config('afterburner-voting.enabled', true))
                @livewire('voting.settings.voting-settings', ['team' => $team], key('voting-settings-'.$team->id))
            @endif
        </div>
    </div>
</x-app-layout>
