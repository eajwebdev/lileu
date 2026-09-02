<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Thin, cached key/value store for owner-editable branding + receipt options.
 * Falls back to config/lileu.php so a fresh install is never blank.
 */
class Settings
{
    private const CACHE_KEY = 'lileu.settings';

    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::query()->pluck('value', 'key')->all();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::all()[$key] ?? null;

        if ($value === null || $value === '') {
            return $default ?? config('lileu.'.self::configPath($key));
        }

        return match ($value) {
            'true' => true,
            'false' => false,
            default => $value,
        };
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return filter_var(self::get($key, $default), FILTER_VALIDATE_BOOL);
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) self::get($key, $default);
    }

    public static function put(string $key, mixed $value, string $group = 'general'): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value, 'group' => $group],
        );

        self::flush();
    }

    public static function putMany(array $values, string $group = 'general'): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value, 'group' => $group],
            );
        }

        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** The brand payload every page (public, portal, admin, receipt) shares. */
    public static function brand(): array
    {
        return [
            'name' => self::get('business_name', config('lileu.business.name')),
            'tagline' => self::get('business_tagline', config('lileu.business.tagline')),
            'phone' => self::get('business_phone', config('lileu.business.phone')),
            'email' => self::get('business_email', config('lileu.business.email')),
            'address' => self::get('business_address', config('lileu.business.address')),
            'facebook' => self::get('business_facebook', config('lileu.business.facebook')),
            'website' => self::get('business_website', config('lileu.business.website')),
            'logo' => self::get('business_logo', '/images/logo.png'),
            'logo_mark' => self::get('business_logo_mark', '/images/logo-mark.png'),
            'currency_symbol' => config('lileu.orders.currency_symbol'),
        ];
    }

    public static function receipt(): array
    {
        return [
            'prefix' => self::get('receipt_prefix', config('lileu.receipt.prefix')),
            'order_prefix' => self::get('order_prefix', config('lileu.receipt.order_prefix')),
            'show_logo' => self::bool('receipt_show_logo', (bool) config('lileu.receipt.show_logo')),
            'footer' => self::get('receipt_footer', config('lileu.receipt.footer')),
            'paper' => self::get('receipt_paper', config('lileu.receipt.paper')),
        ];
    }

    /**
     * The configured logo as a base64 data URI, for print and PDF output.
     * dompdf cannot fetch remote assets, so the bytes have to travel inline.
     */
    public static function logoDataUri(bool $mark = true): ?string
    {
        $brand = self::brand();
        $path = $mark ? $brand['logo_mark'] : $brand['logo'];

        if (blank($path) || str_starts_with($path, 'http')) {
            return null;
        }

        $file = public_path(ltrim($path, '/'));

        if (! is_file($file)) {
            return null;
        }

        $mime = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($file));
    }

    private static function configPath(string $key): string
    {
        return match (true) {
            str_starts_with($key, 'business_') => 'business.'.substr($key, 9),
            str_starts_with($key, 'receipt_') => 'receipt.'.substr($key, 8),
            str_starts_with($key, 'order_') => 'receipt.'.substr($key, 6),
            $key === 'consignment_prefix' => 'receipt.consignment_prefix',
            default => $key,
        };
    }
}
