<?php

namespace App\Enums;

enum CmsPageStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
}
