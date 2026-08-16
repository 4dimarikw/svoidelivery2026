<?php

namespace Domain\Content\Models;

use Database\Factories\Content\SiteSectionFactory;
use Domain\Content\Models\Concerns\HasPublicationState;
use Domain\Content\Observers\SiteSectionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

#[ObservedBy(SiteSectionObserver::class)]
class SiteSection extends Model
{
    use HasFactory;
    use HasPublicationState;

    // Домен вне App\Models — конвенция Laravel не угадает Database\Factories\Content\*
    // сама, без этого переопределения ::factory() падает NotFoundException
    // (тот же паттерн, что у Domain\Auth\Models\User::newFactory()).
    protected static function newFactory(): Factory
    {
        return SiteSectionFactory::new();
    }

    protected $attributes = [
        'fragment' => '',
        'sort_order' => 0,
        'is_active' => true,
    ];

    protected $fillable = [
        'key',
        'title',
        'route_name',
        'fragment',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(ContentBlock::class)->ordered();
    }

    public function publishedBlocks(): HasMany
    {
        return $this->hasMany(ContentBlock::class)->active()->renderable()->ordered();
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(SiteMenuItem::class);
    }

    public function url(): ?string
    {
        // route_name = null — глобальная секция (см. миграцию
        // make_site_sections_route_name_nullable), у неё в принципе нет
        // собственного URL; такая секция никогда не участвует в меню, но
        // метод не должен падать, если его всё же вызовут. Не варнинг —
        // это ожидаемое состояние, а не рассинхрон с роутами.
        if ($this->route_name === null) {
            return null;
        }

        if (! Route::has($this->route_name)) {
            Log::warning('Site section references a missing route.', [
                'site_section_id' => $this->getKey(),
                'route_name' => $this->route_name,
            ]);

            return null;
        }

        try {
            $url = route($this->route_name);
        } catch (Throwable $exception) {
            Log::warning('Site section route URL cannot be generated.', [
                'site_section_id' => $this->getKey(),
                'route_name' => $this->route_name,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        $fragment = ltrim(trim($this->fragment), '#');

        return $fragment === '' ? $url : $url.'#'.$fragment;
    }
}
