<?php

declare(strict_types=1);

namespace App\MoonShine\Support;

use App\MoonShine\Models\MoonshinePermission;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use MoonShine\Support\Enums\Ability;

final class PermissionData
{
    public const array LABELS = [
        'viewAny' => 'Просмотр списка',
        'view' => 'Просмотр записи',
        'create' => 'Создание',
        'update' => 'Редактирование',
        'delete' => 'Удаление',
        'restore' => 'Восстановление',
        'massDelete' => 'Массовое удаление',
        'forceDelete' => 'Окончательное удаление',
    ];

    public function rules(?int $id, mixed $roleId): array
    {
        $abilities = array_column(Ability::cases(), 'value');
        $roleId = filter_var($roleId, FILTER_VALIDATE_INT) ?: 0;

        return [
            'moonshine_user_role_id' => ['required', 'integer', 'exists:moonshine_user_roles,id'],
            'model' => [
                'required', 'string', 'max:255', Rule::in(array_keys(app(PermissionModels::class)->options())),
                Rule::unique('moonshine_permissions', 'model')->where('moonshine_user_role_id', $roleId)->ignore($id),
            ],
            'permissions' => ['present', 'array:'.implode(',', $abilities)],
            'permissions.*' => ['required', 'boolean'],
        ];
    }

    public function validate(MoonshinePermission $item): array
    {
        $data = Validator::make([
            'moonshine_user_role_id' => $item->moonshine_user_role_id,
            'model' => $item->model,
            'permissions' => $item->permissions,
        ], $this->rules($item->exists ? (int) $item->getKey() : null, $item->moonshine_user_role_id))->validate();

        return self::normalize($data['permissions']);
    }

    public static function normalize(array $permissions): array
    {
        return array_map(static fn (mixed $value): mixed => in_array($value, [true, false, 0, 1, '0', '1'], true)
            ? (bool) $value : $value, $permissions);
    }
}
