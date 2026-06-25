<?php

namespace App\Models;

use App\Enums\CmsBlockType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'cms_page_id',
        'type',
        'sort_order',
        'data',
    ];

    protected $casts = [
        'type' => CmsBlockType::class,
        'data' => 'array',
    ];

    public function page()
    {
        return $this->belongsTo(CmsPage::class, 'cms_page_id');
    }
}
