<?php

namespace App\Models;

use App\Enums\NewsArticlePublicationState;
use App\Enums\NewsArticleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NewsArticle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'body',
        'image_key',
        'image_width',
        'image_height',
        'image_alt',
        'image_credit',
        'image_source',
        'image_rights_confirmed_at',
        'image_rights_confirmed_by',
        'status',
        'published_at',
        'seo_title',
        'seo_description',
    ];

    protected $hidden = [
        'image_key',
        'image_source',
        'image_rights_confirmed_at',
        'image_rights_confirmed_by',
        'status',
        'deleted_at',
    ];

    protected $casts = [
        'image_width' => 'integer',
        'image_height' => 'integer',
        'image_rights_confirmed_at' => 'immutable_datetime',
        'status' => NewsArticleStatus::class,
        'published_at' => 'immutable_datetime',
    ];

    public function rightsConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'image_rights_confirmed_by');
    }

    public function scopeEffectivelyPublished(Builder $query): Builder
    {
        return $query
            ->where($query->qualifyColumn('status'), NewsArticleStatus::PUBLISHED->value)
            ->whereNotNull($query->qualifyColumn('published_at'))
            ->where($query->qualifyColumn('published_at'), '<=', now());
    }

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query
            ->orderByDesc($query->qualifyColumn('published_at'))
            ->orderByDesc($query->qualifyColumn('id'));
    }

    public function publicationState(): NewsArticlePublicationState
    {
        if ($this->status !== NewsArticleStatus::PUBLISHED) {
            return NewsArticlePublicationState::DRAFT;
        }

        if ($this->published_at !== null && $this->published_at->isFuture()) {
            return NewsArticlePublicationState::SCHEDULED;
        }

        return NewsArticlePublicationState::PUBLISHED;
    }

    public function isEffectivelyPublished(): bool
    {
        return $this->publicationState() === NewsArticlePublicationState::PUBLISHED;
    }

    public function hasBeenEffectivelyPublished(): bool
    {
        return $this->published_at !== null && ! $this->published_at->isFuture();
    }

    public function isSlugEditable(): bool
    {
        return $this->published_at === null;
    }
}
