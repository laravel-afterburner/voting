<?php

namespace Afterburner\Voting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BallotResponse extends Model
{
    protected $fillable = [
        'ballot_id',
        'ballot_option_id',
        'cast_by_user_id',
        'voter_unit_type',
        'voter_unit_id',
        'proxy_vote_id',
        'ip_address',
        'user_agent',
        'cast_at',
    ];

    protected $casts = [
        'cast_at' => 'datetime',
    ];

    public function ballot(): BelongsTo
    {
        return $this->belongsTo(Ballot::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(BallotOption::class, 'ballot_option_id');
    }

    public function castBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cast_by_user_id');
    }

    public function voterUnit(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'voter_unit_type', 'voter_unit_id');
    }

    public function proxyVote(): BelongsTo
    {
        return $this->belongsTo(ProxyVote::class);
    }
}
