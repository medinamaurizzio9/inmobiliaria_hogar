<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientImportRow extends Model
{
    public const READY = 'ready';

    public const EXISTING = 'existing';

    public const MATCH = 'match';

    public const ERROR = 'error';

    public const CREATED = 'created';

    protected $fillable = ['batch_id', 'cliente_id', 'row_number', 'status', 'detail', 'payload', 'match_accepted'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'match_accepted' => 'boolean'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ClientImportBatch::class, 'batch_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
