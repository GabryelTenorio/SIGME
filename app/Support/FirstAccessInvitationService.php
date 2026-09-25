<?php

namespace App\Support;

use App\Models\FirstAccessToken;
use App\Models\User;
use App\Notifications\FirstAccessInvitation;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FirstAccessInvitationService
{
    public function issue(User $user): string
    {
        if (! $user->requiresFirstAccess()) {
            throw new \LogicException('Somente contas pendentes podem receber convite de primeiro acesso.');
        }

        $plainToken = Str::random(64);

        FirstAccessToken::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            [
                'token_hash' => $this->hash($plainToken),
                'sent_at' => now(),
            ],
        );

        $user->notify(new FirstAccessInvitation($plainToken));

        return $plainToken;
    }

    public function isValid(string $plainToken, string $email): bool
    {
        $invitation = FirstAccessToken::query()
            ->with('user.organization')
            ->where('token_hash', $this->hash($plainToken))
            ->first();

        return $invitation !== null && $this->canBeUsedBy($invitation->user, $email);
    }

    public function complete(string $plainToken, string $email, string $password): ?User
    {
        $user = DB::transaction(function () use ($plainToken, $email, $password): ?User {
            $invitation = FirstAccessToken::query()
                ->where('token_hash', $this->hash($plainToken))
                ->lockForUpdate()
                ->first();

            if (! $invitation) {
                return null;
            }

            $user = User::query()->with('organization')->lockForUpdate()->find($invitation->user_id);

            if (! $user || ! $this->canBeUsedBy($user, $email)) {
                return null;
            }

            $user->forceFill([
                'password' => $password,
                'password_set_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            $invitation->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            return $user;
        });

        if ($user) {
            event(new PasswordReset($user));
        }

        return $user;
    }

    public function revoke(User $user): void
    {
        FirstAccessToken::query()->where('user_id', $user->getKey())->delete();
    }

    private function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    private function canBeUsedBy(User $user, string $email): bool
    {
        return $user->requiresFirstAccess()
            && $user->hasActiveAccess()
            && hash_equals(Str::lower($user->email), Str::lower($email));
    }
}
