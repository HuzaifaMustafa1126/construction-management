<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffInvitation extends Model
{
    protected $fillable = [
        'email', 'pending_email_hash', 'token_hash', 'invited_by', 'accepted_user_id',
        'expires_at', 'accepted_at', 'cancelled_at', 'expired_at', 'last_sent_at', 'send_count',
    ];

    protected $hidden = ['pending_email_hash', 'token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'send_count' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by')->withTrashed();
    }

    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_user_id')->withTrashed();
    }

    public function invitationRoles(): HasMany
    {
        return $this->hasMany(StaffInvitationRole::class);
    }

    public function status(): string
    {
        if ($this->accepted_at) return 'accepted';
        if ($this->cancelled_at) return 'cancelled';
        if ($this->expired_at || $this->expires_at->isPast()) return 'expired';

        return 'pending';
    }

    public function isPending(): bool
    {
        return $this->status() === 'pending';
    }
}
