<?php

declare(strict_types=1);

namespace App\Enums;

enum OAuthClientEventType: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';
    case SECRET_REGENERATED = 'secret_regenerated';
    case ADMIN_REVOKED = 'admin_revoked';
}
