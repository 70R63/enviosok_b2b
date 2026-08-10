<?php

namespace App\Domain\Network\Security;

use App\Domain\Network\Security\Models\NetworkTwoFactorAuthentication;
use App\Domain\Network\Security\Models\NetworkTwoFactorEvent;
use App\Models\User;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final class NetworkTwoFactorService
{
    public function __construct(private readonly Google2FA $totp) {}

    public function configuration(User $user): ?NetworkTwoFactorAuthentication
    {
        return NetworkTwoFactorAuthentication::query()->where('user_id', $user->id)->first();
    }

    public function beginEnrollment(User $user): NetworkTwoFactorAuthentication
    {
        return NetworkTwoFactorAuthentication::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['secret' => $this->totp->generateSecretKey(32)]
        );
    }

    public function qrDataUri(User $user, NetworkTwoFactorAuthentication $configuration): string
    {
        $uri = $this->totp->getQRCodeUrl('ZIGO Network', $user->email, $configuration->secret);
        $renderer = new ImageRenderer(new RendererStyle(240, 2), new SvgImageBackEnd());
        $svg = (new Writer($renderer))->writeString($uri);
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public function enroll(User $user, string $code, Request $request): array|false
    {
        return DB::transaction(function () use ($user, $code, $request): array|false {
            $configuration = NetworkTwoFactorAuthentication::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $configuration || $configuration->enabled_at) return false;
            $timestep = $this->verifyNewer($configuration, $code);
            if ($timestep === false) {
                $this->audit($user, '2FA_CHALLENGE_FAILED', $request);
                return false;
            }

            [$plain, $hashes] = $this->newRecoveryCodes();
            $configuration->update([
                'recovery_code_hashes' => $hashes,
                'last_used_timestep' => $timestep,
                'enabled_at' => now(),
            ]);
            $this->audit($user, '2FA_ENROLLED', $request);
            $this->audit($user, '2FA_CHALLENGE_SUCCESS', $request);
            return $plain;
        });
    }

    public function challenge(User $user, string $code, Request $request): bool
    {
        return DB::transaction(function () use ($user, $code, $request): bool {
            $configuration = NetworkTwoFactorAuthentication::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $configuration?->enabled_at) return false;
            $timestep = $this->verifyNewer($configuration, $code);
            if ($timestep === false) {
                $this->audit($user, '2FA_CHALLENGE_FAILED', $request);
                return false;
            }
            $configuration->update(['last_used_timestep' => $timestep]);
            $this->audit($user, '2FA_CHALLENGE_SUCCESS', $request);
            return true;
        });
    }

    public function consumeRecoveryCode(User $user, string $code, Request $request): bool
    {
        return DB::transaction(function () use ($user, $code, $request): bool {
            $configuration = NetworkTwoFactorAuthentication::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $configuration?->enabled_at) return false;
            $hashes = $configuration->recovery_code_hashes ?? [];
            foreach ($hashes as $index => $hash) {
                if (! Hash::check(Str::upper(trim($code)), $hash)) continue;
                unset($hashes[$index]);
                $configuration->update(['recovery_code_hashes' => array_values($hashes)]);
                $this->audit($user, '2FA_RECOVERY_USED', $request);
                return true;
            }
            $this->audit($user, '2FA_CHALLENGE_FAILED', $request, ['method' => 'recovery']);
            return false;
        });
    }

    public function regenerateRecoveryCodes(User $user, Request $request): array
    {
        [$plain, $hashes] = $this->newRecoveryCodes();
        NetworkTwoFactorAuthentication::query()->where('user_id', $user->id)->whereNotNull('enabled_at')->update(['recovery_code_hashes' => $hashes]);
        $this->audit($user, '2FA_RECOVERY_REGENERATED', $request);
        return $plain;
    }

    public function reset(User $target, User $actor, Request $request): void
    {
        abort_if($target->is($actor) || ! $target->hasRol('sysadmin'), 403);
        DB::transaction(function () use ($target, $actor, $request): void {
            NetworkTwoFactorAuthentication::query()->where('user_id', $target->id)->delete();
            $this->audit($target, '2FA_RESET', $request, [], $actor);
        });
    }

    public function audit(User $user, string $event, Request $request, array $metadata = [], ?User $actor = null): void
    {
        NetworkTwoFactorEvent::query()->create([
            'user_id' => $user->id,
            'actor_user_id' => $actor?->id,
            'event' => $event,
            'ip_hash' => $request->ip() ? hash('sha256', $request->ip().'|'.config('app.key')) : null,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }

    private function verifyNewer(NetworkTwoFactorAuthentication $configuration, string $code): int|false
    {
        return $this->totp->verifyKeyNewer(
            $configuration->secret,
            preg_replace('/\D+/', '', $code),
            (int) ($configuration->last_used_timestep ?? 0),
            1
        );
    }

    private function newRecoveryCodes(): array
    {
        $plain = collect(range(1, 8))->map(fn (): string => Str::upper(Str::random(4).'-'.Str::random(4)))->all();
        return [$plain, array_map(fn (string $code): string => Hash::make($code), $plain)];
    }
}
