<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientCommercialEvent extends Model
{
    public const DUPLICATE_DOCUMENT = 'duplicate_document_attempt';

    public const PHONE_MATCH = 'phone_match';

    public const EMAIL_MATCH = 'email_match';

    public const PROTECTED_OPERATION = 'protected_client_operation';

    public const ADVISOR_REASSIGNED = 'advisor_reassigned';

    protected $fillable = [
        'cliente_id',
        'urbanizacion_id',
        'actor_user_id',
        'original_advisor_id',
        'involved_advisor_id',
        'event_type',
        'match_type',
        'metadata',
        'reviewed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function urbanizacion(): BelongsTo
    {
        return $this->belongsTo(Urbanizacion::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function originalAdvisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_advisor_id');
    }

    public function involvedAdvisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'involved_advisor_id');
    }
}
