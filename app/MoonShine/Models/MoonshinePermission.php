<?php

namespace App\MoonShine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MoonShine\Laravel\Models\MoonshineUserRole;

class MoonshinePermission extends Model
{
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
