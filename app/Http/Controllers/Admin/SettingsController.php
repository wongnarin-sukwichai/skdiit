<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicTitle;
use App\Models\Affiliation;
use App\Models\Journal;
use App\Models\MealOption;
use App\Models\ReviewCriterion;
use App\Models\SiteDocument;
use App\Models\User;
use App\Models\Setting;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * System settings (admin only): submission rules (opening date, deadline, file upload limit, the journal's
 * decision deadline and site, the slides cut-off),
 * academic titles (AcademicTitleController), affiliations (AffiliationController), meals (MealOptionController)
 * and review criteria (ReviewCriterionController).
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
        'journal_deadline.after' => 'validation.journal_after_deadline',
        'journal_deadline.date_format' => 'validation.date',
        'journal_answer_days.min' => 'validation.integer',
        'journal_answer_days.max' => 'validation.integer',
        'slides_cutoff_days.min' => 'validation.integer',
        'slides_cutoff_days.max' => 'validation.integer',
    ];

    public function index(): Response
    {
        return Inertia::render('Admin/Settings', [
            'submission' => [
                'maxMb' => (int) Setting::get('upload_max_mb'),
                'serverMaxMb' => Setting::serverUploadLimitMb(),
                'opens' => Setting::get('submission_opens'),
                'deadline' => Setting::get('submission_deadline'),
                'journalDeadline' => Setting::get('journal_deadline'),
                'journalAnswerDays' => Submission::journalAnswerDays(),
                'slidesCutoffDays' => Setting::get('slides_cutoff_days'),
            ],
            // Every field for editing, plus how many accounts use each title (those cannot be deleted)
            'academicTitles' => AcademicTitle::ordered()->withCount('users')->get()->map(fn (AcademicTitle $title) => [
                ...$title->only('id', 'short_en', 'short_th', 'name_en', 'name_th'),
                'users' => $title->users_count,
            ]),
            // Conference year: what a new year would do (ConferenceYearController)
            'yearSummary' => ConferenceYearController::summary(),
            // Downloadable documents of the public site (the texts come with the shared `site` prop)
            'siteDocuments' => SiteDocument::ordered()->get()->map(fn (SiteDocument $d) => [...$d->toClient(), ...$d->only('name_th', 'name_en', 'file_name')]),
            // Review criteria with how many evaluations scored each (those cannot be deleted)
            'reviewCriteria' => ReviewCriterion::ordered()->withCount('scores')->get()->map(fn (ReviewCriterion $criterion) => [
                ...$criterion->only('id', 'name_th', 'name_en'),
                'description_th' => $criterion->description_th ?? '',
                'description_en' => $criterion->description_en ?? '',
                'scores' => $criterion->scores_count,
            ]),
            // Affiliations with how many accounts use each, and the names registrants typed under "Other"
            'affiliationList' => Affiliation::ordered()->withCount('users')->get()->map(fn (Affiliation $affiliation) => [
                ...$affiliation->only('id', 'code', 'name_en', 'name_th'),
                'users' => $affiliation->users_count,
            ]),
            'typedAffiliations' => User::where('affiliation', Affiliation::OTHER)->whereNotNull('affiliation_other')
                ->selectRaw('affiliation_other as name, count(*) as users')->groupBy('affiliation_other')->orderByDesc('users')->get(),
            // Partner journals offered at the first gate, with how many papers were offered to each (those cannot be deleted)
            'journalList' => Journal::ordered()->withCount('submissions')->get()->map(fn (Journal $journal) => [
                ...$journal->toAdmin(),
                'papers' => $journal->submissions_count,
            ]),
            // Meal preferences with how many registrants and companions chose each (those cannot be deleted)
            'mealList' => MealOption::ordered()->get()->map(fn (MealOption $meal) => [
                ...$meal->only('id', 'code', 'name_en', 'name_th'),
                'users' => $meal->usersCount(),
            ]),
        ]);
    }

    /** Settings › ตั้งค่า: the upload limit and the days an author has to answer a journal offer. */
    public function updateSubmission(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'max_mb' => ['required', 'integer', 'min:1', 'max:'.Setting::serverUploadLimitMb()],
            // Offers not answered within this many days go to the conference track
            'journal_answer_days' => ['required', 'integer', 'min:1', 'max:60'],
        ], self::MESSAGES);

        Setting::put('upload_max_mb', $data['max_mb']);
        Setting::put('journal_answer_days', $data['journal_answer_days']);

        return back()->with('success', 'admin.settings.saved');
    }

    /**
     * Settings › กำหนดการ: one timeline row at a time, so only the fields sent are saved
     * (submission: opens + deadline; journal selection: journal_deadline; slides: slides_cutoff_days).
     */
    public function updateDeadlines(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Authors can submit from the opening time until the deadline; empty = no limit on that side
            'opens' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i'],
            'deadline' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i', ...($request->filled('opens') ? ['after:opens'] : [])],
            // The journal's first selection should be done by this day (a warning only)
            // It starts the day after submissions close, so it cannot be on or before that day
            'journal_deadline' => ['sometimes', 'nullable', 'date_format:Y-m-d', ...(Setting::get('submission_deadline') ? ['after:'.substr(Setting::get('submission_deadline'), 0, 10)] : [])],
            // Slides can be replaced until this many days before the talk; empty = until the talk
            'slides_cutoff_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:60'],
        ], self::MESSAGES);

        $toSetting = fn (?string $value) => $value ? str_replace('T', ' ', $value) : null;
        $keys = ['opens' => 'submission_opens', 'deadline' => 'submission_deadline', 'journal_deadline' => 'journal_deadline', 'slides_cutoff_days' => 'slides_cutoff_days'];

        foreach ($keys as $field => $setting) {
            if (array_key_exists($field, $data)) {
                Setting::put($setting, in_array($field, ['opens', 'deadline'], true) ? $toSetting($data[$field]) : $data[$field]);
            }
        }

        return back()->with('success', 'admin.settings.saved');
    }
}
