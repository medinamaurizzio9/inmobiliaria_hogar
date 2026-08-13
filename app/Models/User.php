<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'cliente_id',
        'name',
        'email',
        'password',
        'must_change_password',
        'estado',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function profilePhotoPath(): ?string
    {
        return $this->cliente_id ? $this->cliente?->foto : $this->foto;
    }

    public function urbanizacionesAsignadas(): BelongsToMany
    {
        return $this->urbanizaciones()
            ->wherePivot('activo', true);
    }

    public function urbanizaciones(): BelongsToMany
    {
        return $this->belongsToMany(Urbanizacion::class, 'urbanizacion_user')
            ->withPivot('activo')
            ->withTimestamps();
    }

    public function asesor(): HasOne
    {
        return $this->hasOne(Asesor::class);
    }

    public function supervisorProfile(): HasOne
    {
        return $this->hasOne(SupervisorProfile::class);
    }
}
