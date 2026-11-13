<?php

namespace App\Support;

use App\Models\Setting;

/**
 * "Close the system" (2026-11-13, Settings › ปิดระบบ, user decisions): when the conference is over admin switches the
 * whole site off by hand. Then only admin gets in (signs in from the closed page); everyone else, signed in or not,
 * sees one page with admin's message (Pages/Closed.vue, App\Http\Middleware\CloseSite).
 */
class SiteClosure
{
    public static function isClosed(): bool
    {
        return (bool) Setting::get('site_closed');
    }

    /** { th, en }; empty texts fall back to the default message on the client. */
    public static function message(): array
    {
        $saved = json_decode((string) Setting::get('site_closed_message'), true);

        return ['th' => (string) ($saved['th'] ?? ''), 'en' => (string) ($saved['en'] ?? '')];
    }

    public static function put(bool $closed, array $message): void
    {
        Setting::put('site_closed', $closed ? '1' : null);
        Setting::put('site_closed_message', json_encode(['th' => trim((string) ($message['th'] ?? '')), 'en' => trim((string) ($message['en'] ?? ''))], JSON_UNESCAPED_UNICODE));
    }
}
