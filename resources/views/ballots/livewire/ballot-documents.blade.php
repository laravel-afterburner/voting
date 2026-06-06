<div @class([
    'rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800' => ! $embedded && ! $inPanel,
    'mt-6' => ! $embedded && ! $inPanel,
])>
    @if ($embedded)
        @if ($canManageDocuments)
            <div class="mb-4 flex justify-end">
                <x-secondary-button type="button" wire:click="openAttachModal" no-spinner>
                    Attach document
                </x-secondary-button>
            </div>
        @endif
    @else
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
    @endif

    <div @class(['mt-4 space-y-3' => ! $embedded, 'space-y-3' => $embedded])>
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
                            <x-action-icon
                                type="view"
                                wire:click="openPreview({{ $document->id }})"
                                wire:loading.attr="disabled"
                                title="Preview document"
                            />
                        @endif
                    @endcan
                    @can('download', $document)
                        <x-action-icon
                            type="download"
                            href="{{ route('teams.documents.download', ['team' => $team, 'document' => $document]) }}"
                            title="Download document"
                        />
                    @endcan
                    @if ($canManageDocuments)
                        <x-action-icon
                            type="delete"
                            wire:click="detachDocument({{ $document->id }})"
                            wire:loading.attr="disabled"
                            class="shrink-0"
                            title="Remove document"
                        />
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
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 dark:focus:border-indigo-600 dark:focus:ring-indigo-600 sm:text-sm">
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
