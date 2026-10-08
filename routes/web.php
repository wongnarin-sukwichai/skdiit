<?php

use App\Http\Controllers\Admin\MySubmissionController;
use App\Http\Controllers\Admin\PublicationController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\ReviewerAssignmentController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SubmissionController;
use App\Http\Controllers\AuthController;
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
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Backend (admin)
// Each route checks the user's role against config/modules.php
Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::inertia('/', 'Admin/Dashboard')->middleware('module:dashboard')->name('dashboard');
    Route::inertia('/registrants', 'Admin/Registrants')->middleware('module:registrants')->name('registrants');

    Route::prefix('settings')->middleware('module:settings')->controller(SettingsController::class)->group(function () {
        Route::get('/', 'index')->name('settings');
        Route::post('/users', 'storeUser')->name('settings.users.store');
        Route::put('/users/{user}', 'updateUser')->name('settings.users.update');
        Route::delete('/users/{user}', 'destroyUser')->name('settings.users.destroy');
        Route::put('/tracks/{track}', 'updateTrack')->name('settings.tracks.update');
        Route::put('/reviewers/{user}/tracks', 'updateReviewerTracks')->name('settings.reviewers.tracks');
        Route::put('/submission', 'updateSubmission')->name('settings.submission');
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

    // Paper files: their author, submission managers or assigned reviewers (checked in the controller)
    Route::get('/submissions/{submission}/file', [SubmissionController::class, 'file'])->name('submissions.file');
    Route::get('/submissions/{submission}/camera-ready', [SubmissionController::class, 'cameraReady'])->name('submissions.camera-ready');

    // Editors and admin: all papers
    Route::prefix('submissions')->middleware('module:submissions')->controller(SubmissionController::class)->group(function () {
        Route::get('/', 'index')->name('submissions');
        Route::get('/export/option-b', [PublicationController::class, 'exportOptionB'])->name('submissions.export-option-b');
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
    });

    // Reviewers: papers assigned to them
    Route::prefix('reviews')->middleware('module:reviews')->controller(ReviewController::class)->group(function () {
        Route::get('/', 'index')->name('reviews');
        Route::get('/{review}', 'show')->name('reviews.show');
        Route::post('/{review}', 'submit')->name('reviews.submit');
    });

    // Sections to be designed later
    Route::get('/{section}', fn (string $section) => Inertia::render('Admin/Placeholder', ['section' => $section]))
        ->whereIn('section', ['payments', 'articles', 'schedule', 'notifications', 'reports'])
        ->middleware('module')
        ->name('section');
});
