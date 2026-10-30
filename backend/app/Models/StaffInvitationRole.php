<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffInvitationRole extends Model
{
    protected $fillable = ['role_id', 'role_name'];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(StaffInvitation::class, 'staff_invitation_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class);
    }
}
