<?php

namespace Afterburner\Voting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BallotOption extends Model
{
    protected $fillable = [
        'ballot_id',
        'label',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function ballot(): BelongsTo
    {
        return $this->belongsTo(Ballot::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(BallotResponse::class);
    }
}
