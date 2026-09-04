<?php

namespace App\Enums;

enum OfficialIdentityProjection: string
{
    case ALIAS = 'alias';
    case NAME_INITIAL = 'name_initial';
    case ANONYMOUS = 'anonymous';
    case TEAM_NAME = 'team_name';
}
