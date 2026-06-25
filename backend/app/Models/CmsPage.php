<?php

namespace App\Models;

use App\Enums\CmsPageStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'status',
        'published_at',
        'seo_title',
        'seo_description',
    ];

    protected $casts = [
        'status' => CmsPageStatus::class,
        'published_at' => 'datetime',
    ];

    public function blocks()
    {
        return $this->hasMany(CmsBlock::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', CmsPageStatus::PUBLISHED->value)
            ->where(function (Builder $query) {
                $query
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }
}
