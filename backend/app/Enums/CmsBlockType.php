<?php

namespace App\Enums;

enum CmsBlockType: string
{
    case HEADING = 'heading';
    case TEXT = 'text';
    case LIST = 'list';
    case IMAGE = 'image';
    case GALLERY = 'gallery';
    case BUTTON = 'button';
    case DOCUMENT_LINK = 'document_link';
}
