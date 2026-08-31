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
     * Handler::reportThrowable() only suppresses the default logger when
     * report() returns something other than exactly `false` (`!== false`):
     * null/true short-circuit it, `false` falls through to the logger. So
     * controlled client errors (4xx) return null to suppress reporting, and
     * server errors (5xx) return false to let them through the normal
     * reporting pipeline — the opposite of what the literal true/false
     * naming suggests.
     */
    public function report(): ?bool
    {
        return $this->status < 500 ? null : false;
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
