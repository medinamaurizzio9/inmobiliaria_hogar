<?php

namespace App\Support;

use App\Models\CommercialSetting;

class WhatsAppLink
{
    public static function url(?string $phone, ?string $name = null): ?string
    {
        $normalized = self::phone($phone);

        if ($normalized === null) {
            return null;
        }

        $url = 'https://wa.me/'.$normalized;
        $message = self::message($name, $phone);

        return $message !== '' ? $url.'?text='.rawurlencode($message) : $url;
    }

    public static function urlWithMessage(?string $phone, string $message): ?string
    {
        $normalized = self::phone($phone);

        if ($normalized === null) {
            return null;
        }

        return 'https://wa.me/'.$normalized.($message !== '' ? '?text='.rawurlencode($message) : '');
    }

    public static function phone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (preg_match('/^591[67]\d{7}$/', $digits) === 1) {
            return $digits;
        }

        if (preg_match('/^[67]\d{7}$/', $digits) !== 1) {
            return null;
        }

        return '591'.$digits;
    }

    private static function message(?string $name, ?string $phone): string
    {
        $template = (string) CommercialSetting::query()
            ->where('key', 'whatsapp_mensaje_predeterminado')
            ->value('value');

        if (trim($template) === '') {
            return '';
        }

        return str_replace(
            ['{{nombre}}', '{{telefono}}'],
            [(string) $name, (string) $phone],
            $template
        );
    }
}
