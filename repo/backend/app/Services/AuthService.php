<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountStatus;
use App\Models\AccountLock;
use App\Models\AuditLogEntry;
use App\Models\FailedLoginAttempt;
use App\Models\User;
use CampusLearn\Auth\LoginThrottlePolicy;
use CampusLearn\Auth\PasswordRule;
use CampusLearn\Support\Exceptions\AccountLocked;
use CampusLearn\Support\Exceptions\InvalidCredentials;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

final class AuthService
{
    public function __construct(
        private readonly PasswordRule $passwordRule,
        private readonly LoginThrottlePolicy $throttlePolicy,
        private readonly int $tokenTtlMinutes,
    ) {
    }

    /**
     * Attempt login. Returns token data on success.
     *
     * @return array{token: string, expires_at: string, user: array<string, mixed>}
     * @throws InvalidCredentials
     * @throws AccountLocked
     */
    public function login(string $email, string $password, string $ip): array
    {
        $user = User::where('email', $email)->first();

        if ($user !== null && $user->status === AccountStatus::Disabled) {
            throw new InvalidCredentials('This account has been disabled.');
        }

        // Reject if there is an active lock
        if ($user !== null) {
            $lock = AccountLock::where('user_id', $user->id)
                ->where('unlock_at', '>', now())
                ->first();
            if ($lock !== null) {
                throw new AccountLocked();
            }
        }

        $windowStart  = now()->subMinutes($this->throttlePolicy->windowMinutes());
        $recentCount  = FailedLoginAttempt::where('email', $email)
            ->where('attempted_at', '>=', $windowStart)
            ->count();

        $credentialsValid = $user !== null && Hash::check($password, $user->password);

        if (! $credentialsValid) {
            FailedLoginAttempt::create([
                'email'        => $email,
                'ip'           => $ip,
                'attempted_at' => now(),
            ]);

            if ($user !== null && $this->throttlePolicy->shouldLock($recentCount + 1)) {
                $unlockAt = now()->addMinutes($this->throttlePolicy->lockDurationMinutes());
                AccountLock::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'locked_at' => now(),
                        'unlock_at' => $unlockAt,
                        'reason'    => 'Too many failed login attempts',
                    ],
                );
                $user->status = AccountStatus::Locked;
                $user->save();

                Log::warning('Account locked due to failed login attempts', ['user_id' => $user->id]);

                AuditLogEntry::create([
                    'actor_user_id'  => null,
                    'action'         => 'account.locked',
                    'target_type'    => 'user',
                    'target_id'      => $user->id,
                    'payload'        => ['reason' => 'failed_login_threshold', 'ip' => $ip],
                    'correlation_id' => (string) request()->attributes->get('correlation_id', ''),
                    'created_at'     => now(),
                ]);

                throw new AccountLocked(
                    'Account locked. Try again after ' . $this->throttlePolicy->lockDurationMinutes() . ' minutes.',
                );
            }

            throw new InvalidCredentials();
        }

        // Success: clear throttle state
        FailedLoginAttempt::where('email', $email)->delete();
        AccountLock::where('user_id', $user->id)->delete();

        if ($user->status === AccountStatus::Locked) {
            $user->status = AccountStatus::Active;
        }
        $user->last_login_at = now();
        $user->save();

        $expiresAt = Carbon::now()->addMinutes($this->tokenTtlMinutes);
        $token     = $user->createToken('api-token', ['*'], $expiresAt);

        Log::info('User authenticated', ['user_id' => $user->id]);

        AuditLogEntry::create([
            'actor_user_id'  => $user->id,
            'action'         => 'user.login',
            'target_type'    => 'user',
            'target_id'      => $user->id,
            'payload'        => ['ip' => $ip],
            'correlation_id' => (string) request()->attributes->get('correlation_id', ''),
            'created_at'     => now(),
        ]);

        return [
            'token'      => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'user'       => $this->userPayload($user),
        ];
    }

    public function logout(User $user, ?string $bearerToken = null): void
    {
        /** @var PersonalAccessToken|null $currentToken */
        $currentToken = $user->currentAccessToken();
        if ($currentToken instanceof PersonalAccessToken) {
            $currentToken->delete();
        } elseif (is_string($bearerToken) && $bearerToken !== '') {
            $tokenModel = PersonalAccessToken::findToken($bearerToken);

            if ($tokenModel !== null && (int) $tokenModel->tokenable_id === (int) $user->id) {
                $tokenModel->delete();
            }
        } else {
            // Fallback for cases where currentAccessToken is not hydrated.
            $user->tokens()->delete();
        }

        AuditLogEntry::create([
            'actor_user_id'  => $user->id,
            'action'         => 'user.logout',
            'target_type'    => 'user',
            'target_id'      => $user->id,
            'payload'        => [],
            'correlation_id' => (string) request()->attributes->get('correlation_id', ''),
            'created_at'     => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function userPayload(User $user): array
    {
        $user->loadMissing('roleAssignments.role');

        return [
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'roles'         => $user->roleAssignments
                ->whereNull('revoked_at')
                ->map(fn ($ra) => [
                    'name'       => $ra->role->name->value,
                    'scope_type' => $ra->scope_type?->value,
                    'scope_id'   => $ra->scope_id,
                ])
                ->values()
                ->all(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }
}
