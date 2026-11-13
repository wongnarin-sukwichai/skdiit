<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Activity log for account and settings changes in the backend (2026-11-13): after a successful non-GET request whose
 * route is listed here, one entry is written. Paper and review steps are not listed: they are in the paper history.
 */
class RecordAdminActivity
{
    /** route name (without "admin.") => [category, action]; the target is the account in the route, if any */
    private const ACCOUNT = [
        'store' => 'account_created',
        'update' => 'account_updated',
        'destroy' => 'account_deleted',
        'password' => 'password_set',
        'send-password' => 'password_sent',
        'suspend' => 'suspended',
        'unsuspend' => 'unsuspended',
        'make-reviewer' => 'role_changed',
        'tracks' => 'reviewer_tracks',
    ];

    /** Settings sections: route prefix => section key (admin.activityLog.section.*) */
    private const SETTINGS = ['settings', 'site', 'academic-titles', 'affiliations', 'journals', 'meals', 'review-criteria', 'tracks', 'announcements.template', 'presentations.rooms', 'presentations.sessions', 'presentations.days'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') || $response->getStatusCode() >= 400 || $request->session()->has('errors')) {
            return $response;
        }
        $name = (string) str($request->route()?->getName())->after('admin.');

        // Accounts: registrants, reviewers, staff
        if (preg_match('/^(registrants|reviewers|staff)\.(.+)$/', $name, $m) && isset(self::ACCOUNT[$m[2]])) {
            $user = $request->route('user');
            ActivityLog::record('account', self::ACCOUNT[$m[2]], $user instanceof User ? $user->email : $request->input('email'), $m[1]);

            return $response;
        }
        if ($name === 'tracks.reviewers') {
            ActivityLog::record('account', 'reviewer_tracks', null, 'tracks');

            return $response;
        }
        if ($name === 'announcements.send') {
            ActivityLog::record('system', 'announcement_sent', null, (string) $request->input('subject'));

            return $response;
        }
        if ($name === 'year.rollover') {
            ActivityLog::record('system', 'year_started', null, (string) $request->input('year'));

            return $response;
        }
        if ($name === 'settings.closure') {
            return $response; // logged as site_closed / site_opened by the controller
        }
        foreach (self::SETTINGS as $prefix) {
            if ($name === $prefix || str_starts_with($name, $prefix.'.')) {
                ActivityLog::record('system', 'settings_changed', null, $prefix);
                break;
            }
        }

        return $response;
    }
}
