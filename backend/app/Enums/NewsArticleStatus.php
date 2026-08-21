<?php

namespace App\Enums;

enum NewsArticleStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
}
