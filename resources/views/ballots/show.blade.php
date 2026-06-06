<x-app-layout :title="\App\Support\PageHeader::make('Voting', detail: $ballot->title)">
    <x-slot name="header">
        <x-afterburner-voting::page-header section="Voting" :detail="$ballot->title" />
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 py-6 sm:py-10 sm:px-6 lg:px-8">
        @livewire('voting.show', ['team' => $team, 'ballot' => $ballot])
    </div>
</x-app-layout>
