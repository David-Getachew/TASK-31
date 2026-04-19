<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Responses\ApiEnvelope;
use CampusLearn\Support\Exceptions\AccountLocked;
use CampusLearn\Support\Exceptions\EditWindowExpired;
use CampusLearn\Support\Exceptions\ConflictException;
use CampusLearn\Support\Exceptions\IdempotencyReplay;
use CampusLearn\Support\Exceptions\InvalidCredentials;
use CampusLearn\Support\Exceptions\InvalidStateTransition;
use CampusLearn\Support\Exceptions\RefundExceedsBalance;
use CampusLearn\Support\Exceptions\SensitiveWordMatched;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

final class ApiExceptionRenderer
{
    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $this->isApiRequest($request)) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => ApiEnvelope::error(
                'UNPROCESSABLE_ENTITY',
                'Validation failed.',
                422,
                ['errors' => $e->errors()],
            ),
            $e instanceof AuthenticationException => ApiEnvelope::error(
                'UNAUTHENTICATED',
                'Authentication is required.',
                401,
            ),
            $e instanceof AuthorizationException => ApiEnvelope::error(
                'FORBIDDEN',
                'You are not permitted to perform this action.',
                403,
            ),
            $e instanceof ModelNotFoundException => ApiEnvelope::error(
                'NOT_FOUND',
                'The requested resource was not found.',
                404,
            ),
            $e instanceof NotFoundHttpException => ApiEnvelope::error(
                'NOT_FOUND',
                'The requested endpoint does not exist.',
                404,
            ),
            $e instanceof MethodNotAllowedHttpException => ApiEnvelope::error(
                'METHOD_NOT_ALLOWED',
                'The HTTP method is not allowed for this endpoint.',
                405,
            ),
            $e instanceof TooManyRequestsHttpException => ApiEnvelope::error(
                'RATE_LIMITED',
                'Too many requests.',
                429,
            ),
            $e instanceof IdempotencyReplay => ApiEnvelope::error(
                'IDEMPOTENCY_KEY_CONFLICT',
                $e->getMessage(),
                409,
            ),
            $e instanceof ConflictException => ApiEnvelope::error(
                'INTERNAL_ERROR',
                $e->getMessage(),
                409,
            ),
            $e instanceof InvalidStateTransition => ApiEnvelope::error(
                'INVALID_STATE_TRANSITION',
                $e->getMessage(),
                422,
            ),
            $e instanceof EditWindowExpired => ApiEnvelope::error(
                'EDIT_WINDOW_EXPIRED',
                $e->getMessage(),
                423,
            ),
            $e instanceof SensitiveWordMatched => new \Illuminate\Http\JsonResponse(['error' => [
                'code'          => 'SENSITIVE_WORDS_BLOCKED',
                'message'       => $e->getMessage(),
                'blocked_terms' => $e->matches,
            ]], 422),
            $e instanceof RefundExceedsBalance => ApiEnvelope::error(
                'REFUND_EXCEEDS_BALANCE',
                $e->getMessage(),
                422,
            ),
            $e instanceof InvalidCredentials => ApiEnvelope::error(
                'INVALID_CREDENTIALS',
                $e->getMessage(),
                401,
            ),
            $e instanceof AccountLocked => ApiEnvelope::error(
                'ACCOUNT_LOCKED',
                $e->getMessage(),
                423,
            ),
            $e instanceof HttpExceptionInterface => ApiEnvelope::error(
                $this->codeForStatus($e->getStatusCode()),
                $e->getMessage() !== '' ? $e->getMessage() : 'HTTP error.',
                $e->getStatusCode(),
            ),
            default => ApiEnvelope::error(
                'INTERNAL_ERROR',
                'An unexpected error occurred.',
                500,
            ),
        };
    }

    private function isApiRequest(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    private function codeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'UNPROCESSABLE_ENTITY',
            423 => 'LOCKED',
            429 => 'RATE_LIMITED',
            503 => 'SERVICE_UNAVAILABLE',
            default => 'HTTP_ERROR',
        };
    }
}
