<?php

namespace App\Services;

use App\Models\Asesor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReservationVisibilityService
{
    public function visibleUserIds(User $user): ?array
    {
        if ($user->hasAnyRole(['super administrador', 'administrador', 'gerente'])) {
            return null;
        }

        if ($user->hasRole('supervisor')) {
            $ids = Asesor::where('supervisor_id', $user->id)->pluck('user_id')->all();
            $ids[] = $user->id;

            return array_values(array_unique($ids));
        }

        if ($user->hasRole('vendedor')) {
            return [$user->id];
        }

        return [$user->id];
    }

    public function apply(Builder $query, User $user): Builder
    {
        $ids = $this->visibleUserIds($user);

        return $ids === null ? $query : $query->whereIn('usuario_id', $ids);
    }

    public function vendedores(User $user): Collection
    {
        $query = User::whereHas('roles', fn ($builder) => $builder->whereIn('name', ['vendedor', 'supervisor']))->orderBy('name');
        $ids = $this->visibleUserIds($user);

        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get();
    }

    public function canFilterUser(User $user, int $userId): bool
    {
        $ids = $this->visibleUserIds($user);

        return $ids === null || in_array($userId, $ids, true);
    }
}
