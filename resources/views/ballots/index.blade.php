<x-app-layout :title="\Afterburner\Voting\Support\PageHeader::make('Voting')">
    <x-slot name="header">
        <x-afterburner-voting::page-header section="Voting" />
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 py-6 sm:py-10 sm:px-6 lg:px-8">
        @livewire('voting.index', ['team' => $team])
    </div>
</x-app-layout>
