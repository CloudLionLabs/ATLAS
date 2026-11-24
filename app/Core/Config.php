<?php
declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static ?array $items = null;

    public static function load(string $file): void
    {
        self::$items = is_file($file) ? require $file : [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$items === null) {
            self::load(base_path('config/app.php'));
        }
        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}
