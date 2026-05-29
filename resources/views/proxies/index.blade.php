<x-app-layout title="Proxy votes">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Proxy votes
        </h2>
    </x-slot>

    <div>
        <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
            @livewire('voting.proxy-manager', ['team' => $team])
        </div>
    </div>
</x-app-layout>
