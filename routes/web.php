<?php

use App\Http\Controllers\Admin\AcademicTitleController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AffiliationController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\ConferenceYearController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExcelExportController;
use App\Http\Controllers\Admin\MealOptionController;
use App\Http\Controllers\Admin\MySubmissionController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PartnerJournalController;
use App\Http\Controllers\Admin\PresentationController;
use App\Http\Controllers\Admin\RegistrantController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\ReviewCriterionController;
use App\Http\Controllers\Admin\ReviewerAssignmentController;
use App\Http\Controllers\Admin\ReviewerController;
use App\Http\Controllers\Admin\SelectionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SiteContentController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SubmissionController;
use App\Http\Controllers\Admin\SubmissionNoteController;
use App\Http\Controllers\Admin\TrackController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Middleware\RecordAdminActivity;
use App\Models\SiteDocument;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Support\Reports;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Frontend (public)
// Home: headline and the public registrant list (this year's registrants, no contact details)
Route::get('/', fn () => Inertia::render('Front/Home', ['registrants' => fn () => User::publicRegistrants()]))->name('home');
Route::get('/schedule', fn () => Inertia::render('Front/Schedule', ['presentations' => PresentationController::publicSchedule()]))->name('schedule');
Route::inertia('/submission', 'Front/Submission')->name('submission');
// Documents admin uploads (Settings › Website content), downloadable by anyone
Route::get('/important', fn () => Inertia::render('Front/Important', ['documents' => SiteDocument::ordered()->get()->map->toClient()]))->name('important');
Route::get('/documents/{siteDocument}', [SiteContentController::class, 'download'])->name('documents.download');
Route::inertia('/contact', 'Front/Contact')->name('contact');
// PDPA: the privacy policy registrants consent to (text in Settings › Website content)
Route::inertia('/privacy', 'Front/Privacy')->name('privacy');

// Authentication (modal forms)
// GET /login and /register show the home page with that modal already open
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => Inertia::render('Front/Home', ['authModal' => 'login', 'registrants' => fn () => User::publicRegistrants()]))->name('login');
    Route::get('/register', fn () => Inertia::render('Front/Home', ['authModal' => 'register', 'registrants' => fn () => User::publicRegistrants()]))->name('register');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/password/resend', [AuthController::class, 'resendPassword'])->name('password.resend');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::put('/password', [AuthController::class, 'changePassword'])->middleware('auth')->name('password.change');
// A registrant of an earlier year joins the current conference year
Route::post('/join-year', [AuthController::class, 'joinYear'])->middleware('auth')->name('join-year');
// "My details" on the public site: own name, phone and affiliation (attendees have no backend "My account")
Route::put('/my-details', [AccountController::class, 'update'])->middleware('auth')->name('my-details.update');
// "My details": an attend-only registrant asks admin to let them submit papers too

// Admin "view as this user": the way back works from any page, also for accounts without backend access
Route::post('/impersonation/stop', [ImpersonationController::class, 'stop'])->middleware('auth')->name('impersonation.stop');

// Backend (admin)
// Each route checks the user's role against config/modules.php
Route::prefix('admin')->name('admin.')->middleware(['auth', RecordAdminActivity::class])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->middleware('module:dashboard')->name('dashboard');
    // Admin views the system as another user, read only (the controller checks who may)
    Route::post('/impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonate');

    // Registrants of this year: admin manages them and decides on requests to submit papers, editors view
    Route::prefix('registrants')->controller(RegistrantController::class)->group(function () {
        Route::get('/', 'index')->middleware('module:registrants')->name('registrants');
        Route::middleware('module:registrants,full')->group(function () {
            Route::post('/', 'store')->name('registrants.store');
            Route::put('/{user}', 'update')->name('registrants.update');
            Route::post('/{user}/password', 'password')->name('registrants.password');
            Route::post('/{user}/send-password', 'sendPassword')->name('registrants.send-password');
            Route::post('/{user}/suspend', 'suspend')->name('registrants.suspend');
            Route::post('/{user}/unsuspend', 'unsuspend')->name('registrants.unsuspend');
            Route::post('/{user}/make-reviewer', 'makeReviewer')->name('registrants.make-reviewer');
            Route::delete('/{user}', 'destroy')->name('registrants.destroy');
        });
    });
    // Excel of the rows a list page shows (registrants, reports, activity log; checked in the controller)
    Route::post('/export', ExcelExportController::class)->name('export');

    // Reports (mock): admin and editor
    Route::get('/reports', fn () => Inertia::render('Admin/Reports', ['report' => Reports::all()]))->middleware('module:reports')->name('reports');

    // Notifications; admin and editors also see the email log (real since 2026-11-13; editors without password emails)
    Route::get('/notifications', [NotificationController::class, 'index'])->middleware('module:notifications')->name('notifications');
    // Everyone with a backend reads their own notifications (bell, home card)
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->whereNumber('notification')->name('notifications.read');
    Route::post('/notifications/emails/{log}/resend', [NotificationController::class, 'resend'])->middleware('module:notifications')->name('notifications.resend');

    // Publication overview and the article archive (mock; the publication work itself is real on each paper's page)
    // Article archive: finished papers of earlier conference years (real)
    Route::get('/articles', [ArticleController::class, 'index'])->middleware('module:articles')->name('articles');

    // Conference event (mock): presentation sessions and the public schedule
    // Presentation schedule (2026-11-12): admin keeps rooms and sessions; editors view (and assign from a paper page)
    Route::prefix('presentations')->middleware('module:presentations')->controller(PresentationController::class)->group(function () {
        Route::get('/', 'index')->name('presentations');
        Route::post('/notify', 'notify')->name('presentations.notify'); // admin and editors (view) alike
        Route::post('/remind-slides', 'remindSlides')->name('presentations.remindSlides');
        Route::get('/remind-slides/preview', 'remindSlidesPreview')->name('presentations.remindSlides.preview');
        Route::middleware('module:presentations,full')->group(function () {
            Route::post('/rooms', 'storeRoom')->name('presentations.rooms.store');
            Route::put('/rooms/{room}', 'updateRoom')->name('presentations.rooms.update');
            Route::delete('/rooms/{room}', 'destroyRoom')->name('presentations.rooms.destroy');
            Route::post('/sessions', 'storeSession')->name('presentations.sessions.store');
            Route::put('/sessions/{session}', 'updateSession')->name('presentations.sessions.update');
            Route::delete('/sessions/{session}', 'destroySession')->name('presentations.sessions.destroy');
            Route::put('/papers/{submission}/move', 'move')->name('presentations.papers.move');
            Route::post('/days', 'storeDay')->name('presentations.days.store');
            Route::delete('/days/{date}', 'destroyDay')->where('date', '\d{4}-\d{2}-\d{2}')->name('presentations.days.destroy');
        });
    });

    // Reviewers: admin manages them, editors can view (writes need the 'full' level)
    Route::prefix('reviewers')->controller(ReviewerController::class)->group(function () {
        Route::get('/', 'index')->middleware('module:reviewers')->name('reviewers');
        Route::middleware('module:reviewers,full')->group(function () {
            Route::post('/', 'store')->name('reviewers.store');
            Route::put('/{user}', 'update')->name('reviewers.update');
            Route::delete('/{user}', 'destroy')->name('reviewers.destroy');
            Route::post('/{user}/password', 'password')->name('reviewers.password');
            Route::post('/{user}/send-password', 'sendPassword')->name('reviewers.send-password');
            Route::post('/{user}/suspend', 'suspend')->name('reviewers.suspend');
            Route::post('/{user}/unsuspend', 'unsuspend')->name('reviewers.unsuspend');
            Route::put('/{user}/tracks', 'updateTracks')->name('reviewers.tracks');
        });
    });

    // Tracks (กลุ่มสาขา)
    // Tracks: editors may look (view), only admin changes names and reviewers (full)
    Route::prefix('tracks')->middleware('module:tracks')->controller(TrackController::class)->group(function () {
        Route::get('/', 'index')->name('tracks');
        Route::put('/{track}', 'update')->middleware('module:tracks,full')->name('tracks.update');
        Route::put('/{track}/reviewers', 'updateReviewers')->middleware('module:tracks,full')->name('tracks.reviewers');
    });

    // Staff accounts: admin, editor, journal
    Route::prefix('staff')->middleware('module:staff,full')->controller(StaffController::class)->group(function () {
        Route::get('/', 'index')->name('staff');
        Route::post('/', 'store')->name('staff.store');
        Route::put('/{user}', 'update')->name('staff.update');
        Route::delete('/{user}', 'destroy')->name('staff.destroy');
        Route::post('/{user}/password', 'password')->name('staff.password');
        Route::post('/{user}/send-password', 'sendPassword')->name('staff.send-password');
        Route::post('/{user}/suspend', 'suspend')->name('staff.suspend');
        Route::post('/{user}/unsuspend', 'unsuspend')->name('staff.unsuspend');
    });

    // My account: every backend role, own record only
    Route::prefix('account')->middleware('module:account')->controller(AccountController::class)->group(function () {
        Route::get('/', 'show')->name('account');
        Route::put('/', 'update')->name('account.update');
    });

    Route::prefix('settings')->middleware('module:settings')->controller(SettingsController::class)->group(function () {
        Route::get('/', 'index')->name('settings');
        Route::put('/submission', 'updateSubmission')->name('settings.submission');
        Route::put('/deadlines', 'updateDeadlines')->name('settings.deadlines');
        Route::put('/schedule/{row}', 'updatePhase')->name('settings.schedule');
        Route::put('/closure', 'updateClosure')->name('settings.closure');
    });
    // Design preview (2026-10-20): the author's paper journey in every status, before it goes on the author pages
    Route::get('/preview/author-journey', fn () => Inertia::render('Admin/Preview/AuthorJourney', [
        'deadline' => Submission::deadline()?->toIso8601String(),
    ]))->middleware('module:settings')->name('preview.author-journey');
    Route::prefix('settings/affiliations')->middleware('module:settings')->controller(AffiliationController::class)->group(function () {
        Route::post('/', 'store')->name('affiliations.store');
        Route::post('/adopt', 'adopt')->name('affiliations.adopt');
        Route::put('/{affiliation}', 'update')->name('affiliations.update');
        Route::put('/{affiliation}/move', 'move')->name('affiliations.move');
        Route::delete('/{affiliation}', 'destroy')->name('affiliations.destroy');
    });
    // Partner journals offered at the first gate
    Route::prefix('settings/journals')->middleware('module:settings')->controller(PartnerJournalController::class)->group(function () {
        Route::post('/', 'store')->name('journals.store');
        Route::put('/{journal}', 'update')->name('journals.update');
        Route::put('/{journal}/move', 'move')->name('journals.move');
        Route::delete('/{journal}', 'destroy')->name('journals.destroy');
    });
    Route::prefix('settings/meals')->middleware('module:settings')->controller(MealOptionController::class)->group(function () {
        Route::post('/', 'store')->name('meals.store');
        Route::put('/{meal}', 'update')->name('meals.update');
        Route::put('/{meal}/move', 'move')->name('meals.move');
        Route::delete('/{meal}', 'destroy')->name('meals.destroy');
    });
    // New conference year and the backup download (admin)
    Route::prefix('settings/year')->middleware('module:settings')->controller(ConferenceYearController::class)->group(function () {
        Route::post('/rollover', 'rollover')->name('year.rollover');
        Route::get('/backup', 'backup')->name('year.backup');
    });

    // Conference information and public-site content
    Route::prefix('settings/site')->middleware('module:settings')->controller(SiteContentController::class)->group(function () {
        Route::put('/conference', 'updateConference')->name('site.conference');
        Route::put('/pages/{page}', 'updatePage')->name('site.page');
        Route::put('/schedule', 'updateSchedule')->name('site.schedule');
        Route::post('/documents', 'storeDocument')->name('site.documents.store');
        Route::post('/documents/{siteDocument}', 'updateDocument')->name('site.documents.update'); // multipart (file replace)
        Route::put('/documents/{siteDocument}/move', 'moveDocument')->name('site.documents.move');
        Route::delete('/documents/{siteDocument}', 'destroyDocument')->name('site.documents.destroy');
    });
    Route::prefix('settings/review-criteria')->middleware('module:settings')->controller(ReviewCriterionController::class)->group(function () {
        Route::post('/', 'store')->name('review-criteria.store');
        Route::put('/{reviewCriterion}', 'update')->name('review-criteria.update');
        Route::put('/{reviewCriterion}/move', 'move')->name('review-criteria.move');
        Route::delete('/{reviewCriterion}', 'destroy')->name('review-criteria.destroy');
    });
    Route::prefix('settings/academic-titles')->middleware('module:settings')->controller(AcademicTitleController::class)->group(function () {
        Route::post('/', 'store')->name('academic-titles.store');
        Route::put('/{academicTitle}', 'update')->name('academic-titles.update');
        Route::put('/{academicTitle}/move', 'move')->name('academic-titles.move');
        Route::delete('/{academicTitle}', 'destroy')->name('academic-titles.destroy');
    });

    // Authors: their own papers
    Route::prefix('my-submissions')->middleware('module:my-submissions')->controller(MySubmissionController::class)->group(function () {
        Route::get('/', 'index')->name('my-submissions');
        Route::get('/create', 'create')->name('my-submissions.create');
        Route::post('/', 'store')->name('my-submissions.store');
        Route::get('/{submission}', 'show')->name('my-submissions.show');
        Route::get('/{submission}/edit', 'edit')->name('my-submissions.edit');
        Route::put('/{submission}', 'update')->name('my-submissions.update');
        Route::post('/{submission}/camera-ready', 'uploadCameraReady')->name('my-submissions.camera-ready');
        Route::post('/{submission}/slides', 'uploadSlides')->name('my-submissions.slides');
        Route::post('/{submission}/journal-answer', 'answerJournal')->name('my-submissions.journal-answer');
    });

    // Every manuscript version / response letter: same people as the paper file (checked in the controller)
    Route::get('/submission-files/{file}', [SubmissionController::class, 'versionFile'])->name('submissions.version-file');

    // Reviewer attachments: that reviewer and the editors/admin (checked in the controller)
    Route::get('/review-files/{review}', [ReviewController::class, 'attachment'])->name('reviews.attachment');
    Route::put('/review-files/{review}/to-author', [ReviewController::class, 'attachmentToAuthor'])->middleware('module:submissions,full')->name('reviews.attachment.to-author');

    // Paper files: their author, submission managers or assigned reviewers (checked in the controller)
    Route::get('/submissions/{submission}/file', [SubmissionController::class, 'file'])->whereNumber('submission')->name('submissions.file');
    Route::get('/submissions/{submission}/camera-ready', [SubmissionController::class, 'cameraReady'])->whereNumber('submission')->name('submissions.camera-ready');
    Route::get('/submissions/{submission}/slides', [SubmissionController::class, 'slides'])->whereNumber('submission')->name('submissions.slides');

    // Selection: the first gate (2026-11-11). Admin, editors and the journal role alike: offer a paper to a journal or
    // send it to the conference track, export the list; the journal role reads a paper here (no Submissions menu)
    Route::prefix('selection')->middleware('module:selection')->controller(SelectionController::class)->group(function () {
        Route::get('/', 'index')->name('selection');
        Route::get('/export', 'export')->name('selection.export');
        Route::post('/remind-offers', 'remindOffers')->middleware('module:selection,full')->name('selection.remind-offers');
        Route::get('/remind-offers/preview', 'remindOffersPreview')->middleware('module:selection,full')->name('selection.remind-offers.preview');
        Route::get('/{submission}', 'show')->whereNumber('submission')->name('selection.show');
        Route::post('/{submission}', 'route')->whereNumber('submission')->middleware('module:selection,full')->name('selection.route');
        Route::put('/{submission}/track', 'updateTrack')->whereNumber('submission')->middleware('module:selection,full')->name('selection.track');
    });

    // Remind one author (editors; the journal role too for a journal offer): checked by Submission::authorCanBeRemindedBy
    Route::post('/submissions/{submission}/remind-author', [SubmissionController::class, 'remindAuthor'])->whereNumber('submission')->name('submissions.remind-author');
    Route::get('/submissions/{submission}/remind-author/preview', [SubmissionController::class, 'remindAuthorPreview'])->whereNumber('submission')->name('submissions.remind-author.preview');

    // Editors and admin: all papers
    Route::prefix('submissions')->middleware('module:submissions')->controller(SubmissionController::class)->group(function () {
        Route::get('/', 'index')->name('submissions');
        Route::get('/export/camera-ready', [SubmissionController::class, 'cameraReadyZip'])->name('submissions.export-camera-ready');
        Route::get('/{submission}', 'show')->name('submissions.show');
        Route::put('/{submission}/presentation', [PresentationController::class, 'assign'])->name('submissions.presentation');
        Route::put('/{submission}/track', 'updateTrack')->name('submissions.track');
        Route::get('/{submission}/mail-preview', 'mailPreview')->name('submissions.mail-preview');
        Route::post('/{submission}/screening', 'screen')->name('submissions.screen');
        Route::post('/{submission}/decision', 'decide')->name('submissions.decide');
        Route::post('/{submission}/review-round', 'startReviewRound')->name('submissions.review-round');
        Route::post('/{submission}/camera-ready/approve', 'approveCameraReady')->name('submissions.camera-ready.approve');
        Route::post('/{submission}/camera-ready/use-latest', 'useLatestCameraReady')->name('submissions.camera-ready.use-latest');
        Route::post('/{submission}/close-revision', 'closeRevision')->name('submissions.close-revision');
        Route::get('/{submission}/close-revision/preview', 'closeRevisionPreview')->name('submissions.close-revision.preview');
        Route::post('/{submission}/camera-ready/fix', 'requestCameraReadyFix')->name('submissions.camera-ready.fix');
        Route::post('/{submission}/cancel-acceptance', 'cancelAcceptance')->name('submissions.cancel-acceptance');

        Route::post('/{submission}/reviews', [ReviewerAssignmentController::class, 'store'])->name('submissions.reviews.store');
        Route::put('/{submission}/reviews/{review}', [ReviewerAssignmentController::class, 'update'])->name('submissions.reviews.update');
        Route::delete('/{submission}/reviews/{review}', [ReviewerAssignmentController::class, 'destroy'])->name('submissions.reviews.destroy');
        Route::post('/{submission}/reviews/{review}/remind', [ReviewerAssignmentController::class, 'remind'])->name('submissions.reviews.remind');

        // Internal notes (admin and editors)
        Route::post('/{submission}/notes', [SubmissionNoteController::class, 'store'])->name('submissions.notes.store');
        Route::put('/{submission}/notes/{note}/pin', [SubmissionNoteController::class, 'pin'])->name('submissions.notes.pin');
        Route::delete('/{submission}/notes/{note}', [SubmissionNoteController::class, 'destroy'])->name('submissions.notes.destroy');
    });

    // Reviewers: papers assigned to them
    Route::prefix('reviews')->middleware('module:reviews')->controller(ReviewController::class)->group(function () {
        Route::get('/', 'index')->name('reviews');
        Route::get('/{review}', 'show')->name('reviews.show');
        Route::post('/{review}', 'submit')->name('reviews.submit');
        Route::post('/{review}/draft', 'draft')->name('reviews.draft');
        Route::post('/{review}/unavailable', 'unavailable')->name('reviews.unavailable');
    });

    // Announcements to a group, and the workflow email templates (editors and admin)
    Route::prefix('announcements')->middleware('module:announcements')->controller(AnnouncementController::class)->group(function () {
        Route::get('/', 'index')->name('announcements');
        Route::post('/', 'send')->name('announcements.send');
        Route::put('/templates/{key}', 'updateTemplate')->name('announcements.template');
        Route::delete('/templates/{key}', 'resetTemplate')->name('announcements.template.reset');
    });

    // Activity log (mock): who did what, when and from where
    Route::get('/activity-log', [ActivityLogController::class, 'index'])->middleware('module:activity-log')->name('activity-log');
    Route::get('/activity-log/rows', [ActivityLogController::class, 'rows'])->middleware('module:activity-log')->name('activity-log.rows');

    // A new module without its page yet: add its key here to show Admin/Placeholder
    // Route::get('/{section}', fn (string $section) => Inertia::render('Admin/Placeholder', ['section' => $section]))
    //     ->whereIn('section', [])->middleware('module')->name('section');
});
