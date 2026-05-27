<?php

namespace Afterburner\Voting\Concerns;

use Afterburner\Documents\Models\Document;
use Afterburner\Voting\Support\DocumentsIntegration;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasLinkedDocuments
{
    public function linkedDocuments(): MorphToMany
    {
        if (! DocumentsIntegration::isAvailable()) {
            throw new \RuntimeException('The documents package is required for ballot document links.');
        }

        return $this->morphToMany(
            Document::class,
            'linkable',
            'document_links',
            'linkable_id',
            'document_id'
        )
            ->withTimestamps()
            ->withPivot(['team_id', 'linked_by_user_id'])
            ->orderBy('document_links.created_at');
    }
}
