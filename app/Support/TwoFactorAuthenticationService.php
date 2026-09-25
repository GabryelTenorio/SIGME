<?php

namespace App\Support;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthenticationService
{
    public function __construct(private readonly Google2FA $google2fa) {}

    /** @return list<string> */
    public function beginEnrollment(User $user): array
    {
        $recoveryCodes = $this->newRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $this->google2fa->generateSecretKey(32),
            'two_factor_recovery_codes' => $this->hashRecoveryCodes($recoveryCodes),
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_step' => null,
        ])->save();

        return $recoveryCodes;
    }

    public function confirmEnrollment(User $user, string $code): bool
    {
        if (! $user->two_factor_secret || ! $this->verifyTotp($user, $code)) {
            return false;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return true;
    }

    public function verifyLoginCode(User $user, string $code): bool
    {
        $normalizedCode = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');

        if (preg_match('/^\d{6}$/', $normalizedCode) === 1) {
            return $this->verifyTotp($user, $normalizedCode);
        }

        return $this->consumeRecoveryCode($user, $normalizedCode);
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(User $user): array
    {
        $recoveryCodes = $this->newRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $this->hashRecoveryCodes($recoveryCodes)])->save();

        return $recoveryCodes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_step' => null,
        ])->save();
    }

    public function qrCodeSvg(User $user): string
    {
        $renderer = new ImageRenderer(new RendererStyle(240, 4), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($this->provisioningUri($user));
    }

    public function provisioningUri(User $user): string
    {
        return $this->google2fa->getQRCodeUrl(
            (string) config('app.name', 'SIGME'),
            $user->email,
            (string) $user->two_factor_secret,
        );
    }

    private function verifyTotp(User $user, string $code): bool
    {
        if (! $user->two_factor_secret || preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        $previousStep = $user->two_factor_last_used_step;
        $verifiedStep = $this->google2fa->verifyKeyNewer(
            $user->two_factor_secret,
            $code,
            $previousStep,
            1,
        );

        if ($verifiedStep === false) {
            return false;
        }

        $updated = User::query()
            ->whereKey($user->getKey())
            ->when(
                $previousStep === null,
                fn ($query) => $query->whereNull('two_factor_last_used_step'),
                fn ($query) => $query->where('two_factor_last_used_step', $previousStep),
            )
            ->update(['two_factor_last_used_step' => $verifiedStep]);

        if ($updated !== 1) {
            return false;
        }

        $user->forceFill(['two_factor_last_used_step' => $verifiedStep]);

        return true;
    }

    private function consumeRecoveryCode(User $user, string $normalizedCode): bool
    {
        if ($normalizedCode === '') {
            return false;
        }

        return DB::transaction(function () use ($user, $normalizedCode): bool {
            $lockedUser = User::query()->lockForUpdate()->find($user->getKey());
            $storedCodes = $lockedUser?->two_factor_recovery_codes ?? [];
            $matchingIndex = array_search(hash('sha256', $normalizedCode), $storedCodes, true);

            if (! $lockedUser || $matchingIndex === false) {
                return false;
            }

            unset($storedCodes[$matchingIndex]);
            $lockedUser->forceFill(['two_factor_recovery_codes' => array_values($storedCodes)])->save();
            $user->forceFill(['two_factor_recovery_codes' => array_values($storedCodes)]);

            return true;
        });
    }

    /** @return list<string> */
    private function newRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn (): string => Str::upper(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    /**
     * @param  list<string>  $recoveryCodes
     * @return list<string>
     */
    private function hashRecoveryCodes(array $recoveryCodes): array
    {
        return array_map(
            fn (string $code): string => hash('sha256', preg_replace('/[^A-Z0-9]/', '', $code) ?? ''),
            $recoveryCodes,
        );
    }
}
