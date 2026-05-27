<?php

namespace Afterburner\Voting\Support;

use Afterburner\Documents\Actions\LinkDocument;
use Afterburner\Documents\Models\Document;
use Illuminate\Support\Facades\Schema;

class DocumentsIntegration
{
    public static function isAvailable(): bool
    {
        return class_exists(Document::class)
            && class_exists(LinkDocument::class)
            && Schema::hasTable('document_links');
    }

    public static function isEnabled(): bool
    {
        if (! config('afterburner-voting.documents_enabled', true)) {
            return false;
        }

        return static::isAvailable();
    }
}
