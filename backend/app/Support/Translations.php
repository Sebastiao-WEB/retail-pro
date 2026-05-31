<?php

namespace App\Support;

class Translations
{
    public static function enum(string $group, ?string $value): string
    {
        if ($value === null || $value === '') {
            return __('app.all');
        }

        $key = "enums.{$group}.{$value}";
        $translated = __($key);

        return $translated !== $key ? $translated : $value;
    }

    public static function intervalType(string $type): string
    {
        return self::enum('interval_type', $type);
    }

    public static function reversalStatus(?string $status): string
    {
        return self::enum('reversal_status', $status);
    }

    public static function balanceStatus(string $status): string
    {
        return self::enum('balance_status', $status);
    }

    public static function saleStatus(?string $status): string
    {
        return self::enum('sale_status', $status);
    }

    public static function paymentMethod(?string $method): string
    {
        return self::enum('payment_method', $method);
    }
}
