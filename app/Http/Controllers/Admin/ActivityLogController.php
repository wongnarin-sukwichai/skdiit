<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Activity log (2026-11-13, real): ActivityLog entries plus the paper history, filtered and paged on the server.
 * Admin sees everything with IP and device; editors (view) see paper and review activity and new registrations.
 */
class ActivityLogController extends Controller
{
    private const FILTERS = ['q', 'category', 'role', 'from', 'to', 'flagged'];

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $isAdmin = $request->user()->accessRole() === 'admin';

        $entries = $this->filtered(ActivityLog::feed($request->user()), $filters)
            ->orderByDesc('at')->orderByDesc('id')
            ->paginate(20)->withQueryString()
            ->through(fn ($row) => $this->toClient($row, $isAdmin));

        $base = ActivityLog::feed($request->user());

        return Inertia::render('Admin/ActivityLog', [
            'entries' => $entries,
            'filters' => $filters,
            'counts' => (clone $base)->selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category'),
            'flaggedCount' => $isAdmin ? (clone $base)->where('flagged', 1)->count() : 0,
        ]);
    }

    /** Every row that matches the filters (up to 5,000), for the Excel export. */
    public function rows(Request $request): JsonResponse
    {
        $isAdmin = $request->user()->accessRole() === 'admin';

        return response()->json($this->filtered(ActivityLog::feed($request->user()), $this->filters($request))
            ->orderByDesc('at')->orderByDesc('id')->limit(5000)->get()
            ->map(fn ($row) => $this->toClient($row, $isAdmin)));
    }

    private function filters(Request $request): array
    {
        return collect(self::FILTERS)->mapWithKeys(fn ($key) => [$key => (string) $request->query($key, '')])->all();
    }

    private function filtered(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['category'] !== '', fn ($q) => $q->where('category', $f['category']))
            ->when($f['role'] !== '', fn ($q) => $f['role'] === 'system' ? $q->whereNull('actor_role') : $q->where('actor_role', $f['role']))
            ->when($f['from'] !== '', fn ($q) => $q->where('at', '>=', $f['from'].' 00:00:00'))
            ->when($f['to'] !== '', fn ($q) => $q->where('at', '<=', $f['to'].' 23:59:59'))
            ->when($f['flagged'] === '1', fn ($q) => $q->where('flagged', 1))
            ->when(trim($f['q']) !== '', function ($q) use ($f) {
                $like = '%'.trim($f['q']).'%';
                $q->where(fn ($w) => $w->where('actor_name', 'like', $like)->orWhere('target', 'like', $like)->orWhere('ip', 'like', $like)->orWhere('detail', 'like', $like));
            });
    }

    private function toClient(object $row, bool $isAdmin): array
    {
        return [
            'id' => "{$row->source}-{$row->id}",
            'source' => $row->source,
            'at' => Carbon::parse($row->at)->toIso8601String(),
            'category' => $row->category,
            'action' => $row->action,
            'actor' => ['name' => $row->actor_name, 'role' => $row->actor_role],
            'target' => $row->target,
            'detail' => $row->detail,
            // Paper history entries are labelled like the paper's own history (utils/submissionEvents.js)
            'event' => $row->source === 'event' ? ['action' => $row->action, 'toStatus' => $row->to_status, 'meta' => json_decode((string) $row->meta, true) ?: []] : null,
            'submissionId' => $row->submission_id,
            'ip' => $isAdmin ? $row->ip : null,
            'device' => $isAdmin ? $row->device : null,
            'flagged' => (bool) $row->flagged,
        ];
    }
}
