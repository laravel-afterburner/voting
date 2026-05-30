<x-app-layout :title="\Afterburner\Voting\Support\PageHeader::make('Voting', isset($ballot) ? 'Edit' : 'Create ballot', isset($ballot) ? $ballot->title : null)">
    <x-slot name="header">
        @if (isset($ballot))
            <x-afterburner-voting::page-header section="Voting" action="Edit" :detail="$ballot->title" />
        @else
            <x-afterburner-voting::page-header section="Voting" action="Create ballot" />
        @endif
    </x-slot>

    <div class="max-w-2xl mx-auto py-10 sm:px-6 lg:px-8">
        @livewire('voting.create', array_filter([
            'team' => $team,
            'ballotId' => isset($ballot) ? $ballot->id : null,
        ], fn ($value) => $value !== null))
    </div>
</x-app-layout>
