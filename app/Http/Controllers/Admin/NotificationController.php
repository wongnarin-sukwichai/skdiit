<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notifications (admin and editors): my notifications, and the email log (2026-11-13, real): every email the
 * system sent, searchable, openable and sent again on request. Editors do not see password emails.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = ['q' => trim((string) $request->query('q')), 'status' => (string) $request->query('status')];
        $base = EmailLog::visibleTo($user);

        $emails = (clone $base)->with('resender')
            ->when(in_array($filters['status'], ['sent', 'failed'], true), fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('to_email', 'like', "%{$filters['q']}%")
                ->orWhere('to_name', 'like', "%{$filters['q']}%")
                ->orWhere('subject', 'like', "%{$filters['q']}%")))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (EmailLog $log) => $log->toClient());

        return Inertia::render('Admin/Notifications', [
            'emails' => $emails,
            'emailFilters' => $filters,
            'failedEmails' => (clone $base)->where('status', 'failed')->count(),
        ]);
    }

    /** Mark one of my notifications as read (opened from the bell or a list). */
    public function read(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->read_at ??= now();
        $notification->save();

        return response()->json(['ok' => true]);
    }

    public function readAll(Request $request): JsonResponse
    {
        UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function resend(Request $request, EmailLog $log): RedirectResponse
    {
        abort_unless(EmailLog::visibleTo($request->user())->whereKey($log->id)->exists(), 403);
        $ok = $log->resend($request->user());

        return back()->with($ok ? 'success' : 'error', [
            'key' => $ok ? 'admin.notificationsPage.toast.resent' : 'admin.notificationsPage.toast.resendFailed',
            'params' => ['email' => $log->to_email],
        ]);
    }
}
