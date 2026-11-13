<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The conference timeline rows the system does not enforce yet (Settings › กำหนดการ, saved since 2026-11-12):
 * registration, screening, review, notification, revision, final decision, camera-ready and scheduling.
 * Each saved row is { start, end, mode, public, perItemDays? }; rows never saved use the default plan on the client
 * (timelinePlan in resources/js/data/mock.js, which also holds each row's icon, who and per-item unit).
 * Shared as the `schedulePlan` prop and read with useSchedulePlan(). The five enforced rows (submission, selection,
 * journal answer, slides, conference) keep their own settings (SettingsController::updateDeadlines, SiteContent).
 */
class SchedulePlan
{
    public const KEY = 'schedule_plan';

    public const ROWS = ['registration', 'screening', 'review', 'notify', 'revision', 'finalDecision', 'cameraReady', 'scheduling'];

    /** @return array<string, array{start: string, end: string, mode: string, public: bool, perItemDays?: int}> */
    public static function all(): array
    {
        return json_decode((string) Setting::get(self::KEY), true) ?: [];
    }

    /**
     * The registration row is the one row enforced (2026-11-13, user decision: lock sign-up only, the rest stays
     * a guide). With mode "lock", sign-up and "join this year" work from start to end (whole days, app timezone):
     * { state: upcoming|open|closed, start, end }. Not saved, or another mode: always open.
     */
    public static function registration(): array
    {
        $row = self::all()['registration'] ?? null;
        if (! $row || ($row['mode'] ?? '') !== 'lock' || empty($row['start']) || empty($row['end'])) {
            return ['state' => 'open', 'start' => $row['start'] ?? null, 'end' => $row['end'] ?? null];
        }
        $today = now()->toDateString();
        $state = $today < $row['start'] ? 'upcoming' : ($today > $row['end'] ? 'closed' : 'open');

        return ['state' => $state, 'start' => $row['start'], 'end' => $row['end']];
    }

    public static function registrationOpen(): bool
    {
        return self::registration()['state'] === 'open';
    }

    public static function put(string $row, array $value): void
    {
        Setting::put(self::KEY, json_encode([...self::all(), $row => $value]));
    }
}
