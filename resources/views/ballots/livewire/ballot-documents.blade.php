<div class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Supporting documents</h4>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Reference materials for this ballot.
            </p>
        </div>
        @if ($canManageDocuments)
            <x-secondary-button type="button" wire:click="openAttachModal" no-spinner>
                Attach document
            </x-secondary-button>
        @endif
    </div>

    <div class="mt-4 space-y-3">
        @forelse ($linkedDocuments as $document)
            <div class="flex items-center justify-between gap-4 rounded-md border border-gray-200 px-4 py-3 dark:border-gray-600">
                <div class="flex min-w-0 flex-1 items-start gap-3">
                    {!! $document->getIconSvg() !!}
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ $document->name }}
                        </p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                            {{ $document->filename }}
                        </p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    @can('view', $document)
                        @if ($document->isPreviewableInBrowser())
                            <button
                                type="button"
                                wire:click="openPreview({{ $document->id }})"
                                wire:loading.attr="disabled"
                                class="p-1 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 rounded"
                                title="Preview document"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                        @endif
                    @endcan
                    @can('download', $document)
                        <a href="{{ route('teams.documents.download', ['team' => $team, 'document' => $document]) }}"
                           class="p-1 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 rounded"
                           title="Download document">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4"></path>
                            </svg>
                        </a>
                    @endcan
                    @if ($canManageDocuments)
                        <button
                            type="button"
                            wire:click="detachDocument({{ $document->id }})"
                            wire:loading.attr="disabled"
                            class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded shrink-0"
                            title="Remove document"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No documents attached yet.
            </p>
        @endforelse
    </div>

    <x-dialog-modal wire:model.live="showAttachModal">
        <x-slot name="title">
            Attach document
        </x-slot>

        <x-slot name="content">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                    <input type="text"
                           wire:model.live.debounce.300ms="documentSearch"
                           placeholder="Search by name or filename"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 sm:text-sm">
                </div>

                <div class="max-h-80 space-y-2 overflow-y-auto">
                    @forelse ($availableDocuments as $document)
                        <div class="flex items-center justify-between gap-3 rounded-md border border-gray-200 px-3 py-2 dark:border-gray-600">
                            <div class="flex min-w-0 items-center gap-2">
                                {!! $document->getIconSvg() !!}
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $document->name }}</p>
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $document->filename }}</p>
                                </div>
                            </div>
                            <x-secondary-button type="button" wire:click="attachDocument({{ $document->id }})" no-spinner>
                                Attach
                            </x-secondary-button>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            No completed documents available to attach.
                        </p>
                    @endforelse
                </div>
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="closeAttachModal">
                Cancel
            </x-secondary-button>
        </x-slot>
    </x-dialog-modal>

    @if ($previewDocument && $previewUrl)
        @include('afterburner-documents::components.document-preview-modal', [
            'team' => $team,
            'document' => $previewDocument,
            'previewUrl' => $previewUrl,
        ])
    @endif
</div>
