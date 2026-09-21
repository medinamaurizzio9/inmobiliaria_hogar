<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientImportBatch extends Model
{
    protected $fillable = ['user_id', 'urbanizacion_id', 'original_name', 'file_hash', 'status', 'total_rows', 'created_rows', 'existing_rows', 'match_rows', 'error_rows', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ClientImportRow::class, 'batch_id')->orderBy('row_number');
    }
}
