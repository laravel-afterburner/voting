<x-app-layout :title="\App\Support\PageHeader::make('Voting', isset($ballot) ? 'Edit' : 'Create ballot', isset($ballot) ? $ballot->title : null)">
    <x-slot name="header">
        @if (isset($ballot))
            <x-afterburner-voting::page-header section="Voting" action="Edit" :detail="$ballot->title" />
        @else
            <x-afterburner-voting::page-header section="Voting" action="Create ballot" />
        @endif
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 py-6 sm:py-10 sm:px-6 lg:px-8">
        @livewire('voting.create', array_filter([
            'team' => $team,
            'ballotId' => isset($ballot) ? $ballot->id : null,
        ], fn ($value) => $value !== null))
    </div>
</x-app-layout>
