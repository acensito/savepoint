<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ApiException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>|null  $errors
     * @param  array<string, string|list<string>>  $headers
     */
    public function __construct(
        public readonly ApiErrorCode $errorCode,
        public readonly int $status,
        string $message,
        public readonly ?array $errors = null,
        public readonly array $headers = [],
    ) {
        parent::__construct($message);
    }

    /**
     * Returning false tells Laravel to suppress reporting for controlled
     * client errors. Null lets server errors continue through the normal
     * reporting pipeline.
     */
    public function report(): ?bool
    {
        return $this->status < 500 ? false : null;
    }

    public function render(Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return response()->json($this->payload(), $this->status, $this->headers);
    }

    /**
     * @return array<string, string|int|array<string, list<string>>>
     */
    public function payload(): array
    {
        return array_filter([
            'code' => $this->errorCode->value,
            'status' => $this->status,
            'message' => $this->getMessage(),
            'errors' => $this->errors,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
