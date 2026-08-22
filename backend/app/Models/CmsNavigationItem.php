<?php

namespace App\Models;

use App\Enums\CmsNavigationSlot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsNavigationItem extends Model
{
    use HasFactory;

    public const RESERVED_PAGE_SLUGS = [
        'nosotros',
        'contacto',
        'federarse',
        'documentos',
    ];

    protected $fillable = [
        'cms_page_id',
        'slot',
        'label',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'slot' => CmsNavigationSlot::class,
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function cmsPage(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('slot')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('slot', CmsNavigationSlot::CLUB->value)
            ->where('is_active', true)
            ->whereHas('cmsPage', fn (Builder $pages): Builder => $pages
                ->published()
                ->whereNotIn('slug', self::RESERVED_PAGE_SLUGS));
    }

    public static function isReservedPageSlug(string $slug): bool
    {
        return in_array($slug, self::RESERVED_PAGE_SLUGS, true);
    }

    public static function isValidLabel(string $label): bool
    {
        $trimmed = trim($label);

        return $trimmed !== ''
            && mb_strlen($trimmed) <= 80
            && preg_match('/[\x00-\x1F\x7F]/u', $trimmed) !== 1
            && ! str_contains($trimmed, '<')
            && ! str_contains($trimmed, '>')
            && preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/\/|\/)/iu', $trimmed) !== 1;
    }
}
