<?php

declare(strict_types=1);

namespace App\Enums\Api\V0;

enum ApiErrorCode: string
{
    // General
    case NOT_FOUND = 'NOT_FOUND';
    case VALIDATION_FAILED = 'VALIDATION_FAILED';
    case UNAUTHENTICATED = 'UNAUTHENTICATED';
    case FORBIDDEN = 'FORBIDDEN';
    case SERVER_ERROR = 'SERVER_ERROR';
    case UNEXPECTED_ERROR = 'UNEXPECTED_ERROR';

    // Authentication
    case PASSWORD_LOGIN_UNAVAILABLE = 'PASSWORD_LOGIN_UNAVAILABLE';
    case INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    case VERIFICATION_INVALID = 'VERIFICATION_INVALID';
    case ALREADY_VERIFIED = 'ALREADY_VERIFIED';

    // Query Builder Errors
    case INVALID_QUERY_PARAMETER = 'INVALID_QUERY_PARAMETER';
}
