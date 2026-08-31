<?php

namespace App\Exceptions;

enum ApiErrorCode: string
{
    case UNAUTHENTICATED = 'UNAUTHENTICATED';
    case INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    case TWO_FACTOR_CHALLENGE_EXPIRED = 'TWO_FACTOR_CHALLENGE_EXPIRED';
    case INVALID_TWO_FACTOR_CODE = 'INVALID_TWO_FACTOR_CODE';
    case FORBIDDEN = 'FORBIDDEN';
    case NOT_FOUND = 'NOT_FOUND';
    case VALIDATION_ERROR = 'VALIDATION_ERROR';
    case RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';
    case SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';
    // Cualquier HttpExceptionInterface no cubierto por un código más
    // específico de arriba (405, 415, 400, 409...): conserva el status HTTP
    // real de la excepción en vez de caer en el catch-all de INTERNAL_ERROR.
    case HTTP_ERROR = 'HTTP_ERROR';
    case INTERNAL_ERROR = 'INTERNAL_ERROR';
}
