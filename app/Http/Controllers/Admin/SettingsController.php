<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicTitle;
use App\Models\Affiliation;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * System settings (admin only): submission rules (opening date, deadline, file upload limit),
 * academic titles (AcademicTitleController) and affiliations (AffiliationController).
 * Accounts live in Staff / Reviewers, track names in Tracks.
 */
class SettingsController extends Controller
{
    /** Validation messages are translation keys; the Vue client renders them in the active language. */
    private const MESSAGES = [
        'required' => 'validation.required',
        'integer' => 'validation.integer',
        'max_mb.min' => 'validation.upload_range',
        'max_mb.max' => 'validation.upload_range',
        'opens.date_format' => 'validation.date',
        'deadline.date_format' => 'validation.date',
        'deadline.after' => 'validation.deadline_after_opens',
    ];

    public function index(): Response
    {
        return Inertia::render('Admin/Settings', [
            'submission' => [
                'maxMb' => (int) Setting::get('upload_max_mb'),
                'serverMaxMb' => Setting::serverUploadLimitMb(),
                'opens' => Setting::get('submission_opens'),
                'deadline' => Setting::get('submission_deadline'),
            ],
            // Every field for editing, plus how many accounts use each title (those cannot be deleted)
            'academicTitles' => AcademicTitle::ordered()->withCount('users')->get()->map(fn (AcademicTitle $title) => [
                ...$title->only('id', 'short_en', 'short_th', 'name_en', 'name_th'),
                'users' => $title->users_count,
            ]),
            // Affiliations with how many accounts use each, and the names registrants typed under "Other"
            'affiliationList' => Affiliation::ordered()->withCount('users')->get()->map(fn (Affiliation $affiliation) => [
                ...$affiliation->only('id', 'code', 'name_en', 'name_th'),
                'users' => $affiliation->users_count,
            ]),
            'typedAffiliations' => User::where('affiliation', Affiliation::OTHER)->whereNotNull('affiliation_other')
                ->selectRaw('affiliation_other as name, count(*) as users')->groupBy('affiliation_other')->orderByDesc('users')->get(),
        ]);
    }

    public function updateSubmission(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'max_mb' => ['required', 'integer', 'min:1', 'max:'.Setting::serverUploadLimitMb()],
            // Authors can submit from the opening date until the deadline; empty = no limit on that side
            'opens' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'deadline' => ['nullable', 'date_format:Y-m-d\TH:i', ...($request->filled('opens') ? ['after:opens'] : [])],
        ], self::MESSAGES);

        $toSetting = fn (?string $value) => $value ? str_replace('T', ' ', $value) : null;

        Setting::put('upload_max_mb', $data['max_mb']);
        Setting::put('submission_opens', $toSetting($data['opens']));
        Setting::put('submission_deadline', $toSetting($data['deadline']));

        return back()->with('success', 'admin.settings.saved');
    }
}
