<?php

namespace App\MoonShine\Models;

use App\MoonShine\Support\PermissionCache;
use App\MoonShine\Support\PermissionData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MoonShine\Laravel\Models\MoonshineUserRole;

class MoonshinePermission extends Model
{
    protected static function booted(): void
    {
        static::saving(static function (self $permission): void {
            $permission->permissions = PermissionData::normalize($permission->permissions ?? []);
        });

        $invalidate = static function (self $permission): void {
            $cache = app(PermissionCache::class);
            $cache->forget((int) $permission->moonshine_user_role_id, $permission->model);

            if ($permission->getRawOriginal('model') !== null) {
                $cache->forget((int) $permission->getRawOriginal('moonshine_user_role_id'), $permission->getRawOriginal('model'));
            }
        };

        static::saved($invalidate);
        static::deleted($invalidate);
    }

    protected $fillable = [
        'moonshine_user_role_id',
        'model',
        'permissions',
    ];

    public function moonshineUserRole(): BelongsTo
    {
        return $this->belongsTo(MoonshineUserRole::class);
    }

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }
}
