<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * One entry of the activity log (2026-11-13). record() writes it with the request's IP and device; the page reads
 * these rows together with the paper history (submission_events) through feed(). Nothing removes old entries on its
 * own (no scheduled work in this system); `php artisan model:prune --model=App\\Models\\ActivityLog` drops those older than a year.
 */
class ActivityLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'actor_name', 'actor_role', 'category', 'action', 'target', 'detail', 'ip', 'device', 'flagged'];

    /** Worth a second look: shown in red with a flag (failed sign-ins, deletions, impersonation, passwords set by admin). */
    public const FLAGGED = ['login_failed', 'account_deleted', 'password_set', 'suspended', 'impersonation_start', 'year_started', 'backup_downloaded'];

    /** Paper history actions that belong to reviews (the rest are "paper"). */
    public const REVIEW_ACTIONS = ['reviewer_assigned', 'reviewer_removed', 'review_due_changed', 'review_submitted', 'review_reminded', 'review_unavailable', 'review_round_started'];

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subYear());
    }

    /** Write an entry; the actor is the signed-in user unless given (null + no session = system). Never throws. */
    public static function record(string $category, string $action, ?string $target = null, ?string $detail = null, ?User $actor = null): void
    {
        try {
            $actor ??= Auth::user();
            $request = request();
            static::create([
                'user_id' => $actor?->id,
                'actor_name' => $actor?->name,
                'actor_role' => $actor?->accessRole(),
                'category' => $category,
                'action' => $action,
                'target' => $target ? mb_substr($target, 0, 255) : null,
                'detail' => $detail ? mb_substr($detail, 0, 255) : null,
                'ip' => $request?->ip(),
                'device' => static::device((string) $request?->userAgent()),
                'flagged' => in_array($action, self::FLAGGED, true),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** "Chrome · Windows" from a user agent. */
    public static function device(string $agent): ?string
    {
        if ($agent === '') {
            return null;
        }
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Other',
        };
        $os = match (true) {
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => '',
        };

        return trim("{$browser} · {$os}", ' ·');
    }

    /**
     * The log as the page reads it: these entries plus the paper history, in one query with the same columns.
     * Editors (view access) only get paper and review activity and new registrations.
     */
    public static function feed(User $viewer): QueryBuilder
    {
        $role = "CASE WHEN u.role = 'user' THEN (CASE WHEN u.participation = 'attend' THEN 'attendee' ELSE 'author' END) ELSE u.role END";
        $reviewActions = "'".implode("','", self::REVIEW_ACTIONS)."'";

        $log = DB::table('activity_logs')->selectRaw("'log' as source, id, created_at as at, actor_name, actor_role, category, action, target, detail, null as meta, null as to_status, ip, device, flagged, null as submission_id");
        $events = DB::table('submission_events as e')
            ->join('submissions as s', 's.id', '=', 'e.submission_id')
            ->leftJoin('users as u', 'u.id', '=', 'e.user_id')
            ->selectRaw("'event' as source, e.id, e.created_at as at, u.name as actor_name, {$role} as actor_role, CASE WHEN e.action IN ({$reviewActions}) THEN 'review' ELSE 'paper' END as category, e.action, s.code as target, null as detail, e.meta, e.to_status, null as ip, null as device, 0 as flagged, s.id as submission_id");

        $query = DB::query()->fromSub($log->unionAll($events), 'a');
        if ($viewer->accessRole() !== 'admin') {
            $query->where(fn ($q) => $q->whereIn('category', ['paper', 'review'])->orWhere('action', 'registered'));
        }

        return $query;
    }
}
