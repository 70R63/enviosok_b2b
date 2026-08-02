<?php

namespace App\Services\Marketing;

use App\Models\ZigoPublicChannel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PublicChannelService
{
    public const SOCIAL_CHANNELS = ['facebook', 'instagram', 'tiktok'];
    public const CONTACT_CHANNELS = [
        'whatsapp',
        'commercial_phone',
        'support_phone',
        'commercial_email',
        'support_email',
    ];
    public const CHANNELS = [
        'facebook',
        'instagram',
        'tiktok',
        'whatsapp',
        'commercial_phone',
        'support_phone',
        'commercial_email',
        'support_email',
    ];

    private const SOCIAL_DOMAINS = [
        'facebook' => 'facebook.com',
        'instagram' => 'instagram.com',
        'tiktok' => 'tiktok.com',
    ];

    public function active(): Collection
    {
        if (!$this->tableExists()) {
            return $this->fallbackChannels();
        }

        if (!ZigoPublicChannel::query()->exists()) {
            return $this->fallbackChannels();
        }

        return ZigoPublicChannel::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ZigoPublicChannel $channel): array => $this->serialize($channel))
            ->filter(fn (array $channel): bool => $channel['url'] !== null)
            ->values();
    }

    public function get(string $key): ?array
    {
        if (!in_array($key, self::CHANNELS, true)) {
            return null;
        }

        if ($this->tableExists()) {
            $channel = ZigoPublicChannel::query()
                ->where('channel', $key)
                ->first();

            if ($channel) {
                return $this->serialize($channel);
            }
        }

        return $this->fallbackChannels()->firstWhere('channel', $key);
    }

    public function normalizedUrl(string $channel, ?string $value, ?string $url = null): ?string
    {
        $value = $this->clean($value);
        $url = $this->clean($url);

        if (isset(self::SOCIAL_DOMAINS[$channel])) {
            if ($url !== null) {
                return $this->safeSocialUrl($channel, $url);
            }

            if ($value === null) {
                return null;
            }

            $handle = ltrim($value, '@');
            if (!preg_match('/^[A-Za-z0-9._-]+$/', $handle)) {
                return null;
            }

            $prefix = $channel === 'tiktok' ? '@' : '';

            return 'https://' . self::SOCIAL_DOMAINS[$channel] . '/' . $prefix . $handle;
        }

        if ($channel === 'whatsapp') {
            $digits = preg_replace('/\D+/', '', (string) $value);

            return $digits !== '' ? 'https://wa.me/' . $digits : null;
        }

        if (in_array($channel, ['commercial_phone', 'support_phone'], true)) {
            $phone = preg_replace('/[^0-9+]/', '', (string) $value);
            $phone = preg_replace('/(?!^)\+/', '', (string) $phone);

            return $phone !== '' ? 'tel:' . $phone : null;
        }

        if (in_array($channel, ['commercial_email', 'support_email'], true)
            && $value !== null
            && filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'mailto:' . $value;
        }

        return null;
    }

    public function isAllowedSocialUrl(string $channel, string $url): bool
    {
        return $this->safeSocialUrl($channel, $url) !== null;
    }

    private function serialize(ZigoPublicChannel $channel): array
    {
        return [
            'channel' => $channel->channel,
            'label' => $channel->label ?: Str::headline($channel->channel),
            'value' => $channel->value,
            'url' => $this->normalizedUrl($channel->channel, $channel->value, $channel->url),
            'is_active' => $channel->is_active,
            'sort_order' => $channel->sort_order,
        ];
    }

    private function fallbackChannels(): Collection
    {
        return collect(self::SOCIAL_CHANNELS)->map(function (string $channel, int $index): array {
            $url = config('social.' . $channel);

            return [
                'channel' => $channel,
                'label' => ucfirst($channel),
                'value' => null,
                'url' => is_string($url) ? $this->normalizedUrl($channel, null, $url) : null,
                'is_active' => true,
                'sort_order' => ($index + 1) * 10,
            ];
        })->filter(fn (array $channel): bool => $channel['url'] !== null)->values();
    }

    private function safeSocialUrl(string $channel, string $url): ?string
    {
        if (!isset(self::SOCIAL_DOMAINS[$channel]) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $domain = self::SOCIAL_DOMAINS[$channel];
        $validHost = $host === $domain || str_ends_with($host, '.' . $domain);

        if (($parts['scheme'] ?? '') !== 'https'
            || !$validHost
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return null;
        }

        return $url;
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('zigo_public_channels');
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
