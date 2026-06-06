<x-app-layout :title="\App\Support\PageHeader::make('Voting', detail: 'Proxy votes')">
    <x-slot name="header">
        <x-afterburner-voting::page-header section="Voting" detail="Proxy votes" />
    </x-slot>

    <div>
        <div class="max-w-4xl mx-auto px-4 py-6 sm:py-10 sm:px-6 lg:px-8">
            @livewire('voting.proxy-manager', ['team' => $team])
        </div>
    </div>
</x-app-layout>
