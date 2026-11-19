<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attachment extends Model
{
    use BelongsToCompany, SoftDeletes;

    public const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    protected $fillable = ['category'];

    protected $hidden = ['disk', 'file_path', 'thumbnail_path'];

    protected function casts(): array
    {
        return [
            'uploaded_by' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
