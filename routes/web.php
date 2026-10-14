<?php

use App\Http\Controllers\Admin\AcademicTitleController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\AffiliationController;
use App\Http\Controllers\Admin\MySubmissionController;
use App\Http\Controllers\Admin\PublicationController;
use App\Http\Controllers\Admin\ExcelExportController;
use App\Http\Controllers\Admin\ReviewerController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\ReviewerAssignmentController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SubmissionController;
use App\Http\Controllers\Admin\SubmissionNoteController;
use App\Http\Controllers\Admin\TrackController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ImpersonationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Frontend (public)
Route::inertia('/', 'Front/Home')->name('home');
Route::inertia('/about', 'Front/About')->name('about');
Route::inertia('/schedule', 'Front/Schedule')->name('schedule');
Route::inertia('/submission', 'Front/Submission')->name('submission');
Route::inertia('/important', 'Front/Important')->name('important');
Route::inertia('/contact', 'Front/Contact')->name('contact');

// Authentication (modal forms)
// GET /login and /register show the home page with that modal already open
Route::middleware('guest')->group(function () {
    Route::inertia('/login', 'Front/Home', ['authModal' => 'login'])->name('login');
    Route::inertia('/register', 'Front/Home', ['authModal' => 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/password/resend', [AuthController::class, 'resendPassword'])->name('password.resend');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::put('/password', [AuthController::class, 'changePassword'])->middleware('auth')->name('password.change');

// Admin "view as this user": the way back works from any page, also for accounts without backend access
Route::post('/impersonation/stop', [ImpersonationController::class, 'stop'])->middleware('auth')->name('impersonation.stop');

// Backend (admin)
// Each route checks the user's role against config/modules.php
Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->middleware('module:dashboard')->name('dashboard');
    // Admin views the system as another user, read only (the controller checks who may)
    Route::post('/impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonate');

    Route::inertia('/registrants', 'Admin/Registrants')->middleware('module:registrants')->name('registrants');
    // Excel of what a sample-data page shows (registrants, payments, reports, activity log; checked in the controller)
    Route::post('/export', ExcelExportController::class)->name('export');

    // Reports (mock): admin and editor see every tab, finance the finance tab only
    Route::inertia('/reports', 'Admin/Reports')->middleware('module:reports')->name('reports');

    // Notifications (mock until email/SMTP is decided); admin also sees the email log
    Route::inertia('/notifications', 'Admin/Notifications')->middleware('module:notifications')->name('notifications');

    // Payments (mock until the client confirms fees): finance/admin check slips, authors see their own
    Route::inertia('/payments', 'Admin/Payments')->middleware('module:payments')->name('payments');

    // Publication overview and the article archive (mock; the publication work itself is real on each paper's page)
    Route::inertia('/publication', 'Admin/Publication')->middleware('module:publication')->name('publication');
    Route::inertia('/articles', 'Admin/Articles')->middleware('module:articles')->name('articles');

    // Conference event (mock): presentation sessions and the public schedule
    Route::inertia('/presentations', 'Admin/Presentations')->middleware('module:presentations')->name('presentations');
    Route::inertia('/schedule', 'Admin/Schedule')->middleware('module:schedule')->name('schedule');

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

    // Staff accounts: admin, editor, finance
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
    });
    Route::prefix('settings/affiliations')->middleware('module:settings')->controller(AffiliationController::class)->group(function () {
        Route::post('/', 'store')->name('affiliations.store');
        Route::post('/adopt', 'adopt')->name('affiliations.adopt');
        Route::put('/{affiliation}', 'update')->name('affiliations.update');
        Route::put('/{affiliation}/move', 'move')->name('affiliations.move');
        Route::delete('/{affiliation}', 'destroy')->name('affiliations.destroy');
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
        Route::post('/{submission}/option', 'chooseOption')->name('my-submissions.option');
        Route::post('/{submission}/camera-ready', 'uploadCameraReady')->name('my-submissions.camera-ready');
    });

    // Every manuscript version / response letter: same people as the paper file (checked in the controller)
    Route::get('/submission-files/{file}', [SubmissionController::class, 'versionFile'])->name('submissions.version-file');

    // Reviewer attachments: that reviewer and the editors/admin (checked in the controller)
    Route::get('/review-files/{review}', [ReviewController::class, 'attachment'])->name('reviews.attachment');

    // Paper files: their author, submission managers or assigned reviewers (checked in the controller)
    Route::get('/submissions/{submission}/file', [SubmissionController::class, 'file'])->whereNumber('submission')->name('submissions.file');
    Route::get('/submissions/{submission}/camera-ready', [SubmissionController::class, 'cameraReady'])->whereNumber('submission')->name('submissions.camera-ready');

    // Editors and admin: all papers
    Route::prefix('submissions')->middleware('module:submissions')->controller(SubmissionController::class)->group(function () {
        Route::get('/', 'index')->name('submissions');
        Route::get('/export/option-b', [PublicationController::class, 'exportOptionB'])->name('submissions.export-option-b');
        Route::get('/export/camera-ready', [SubmissionController::class, 'cameraReadyZip'])->name('submissions.export-camera-ready');
        Route::get('/{submission}', 'show')->name('submissions.show');
        Route::post('/{submission}/journal-result', [PublicationController::class, 'journalResult'])->name('submissions.journal-result');
        Route::put('/{submission}/presentation', [PublicationController::class, 'schedule'])->name('submissions.presentation');
        Route::put('/{submission}/track', 'updateTrack')->name('submissions.track');
        Route::post('/{submission}/screening', 'screen')->name('submissions.screen');
        Route::post('/{submission}/decision', 'decide')->name('submissions.decide');
        Route::post('/{submission}/review-round', 'startReviewRound')->name('submissions.review-round');

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

    // Activity log (mock): who did what, when and from where
    Route::inertia('/activity-log', 'Admin/ActivityLog')->middleware('module:activity-log')->name('activity-log');

    // A new module without its page yet: add its key here to show Admin/Placeholder
    // Route::get('/{section}', fn (string $section) => Inertia::render('Admin/Placeholder', ['section' => $section]))
    //     ->whereIn('section', [])->middleware('module')->name('section');
});
