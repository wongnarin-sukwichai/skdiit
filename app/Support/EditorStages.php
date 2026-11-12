<?php

namespace App\Support;

use App\Enums\SubmissionStatus;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;

/**
 * The editor's work along the conference schedule, for the slim bar at the top of every backend page
 * (Components/Admin/Journey/EditorTimeline, 2026-11-12). Shared as the `editorStages` prop to staff who manage
 * submissions (admin, editors): the work queues, a few stage counts, and the enforced dates the client lays over
 * the saved schedule (withRealDates()). A handful of count queries per page.
 */
class EditorStages
{
    public static function forClient(?User $user): ?array
    {
        if (! $user || $user->moduleAccess('submissions') !== 'full') {
            return null;
        }
        $year = fn () => Submission::currentYear();

        return [
            'queues' => Submission::queueCounts(),
            // The Submissions tabs: { count: waiting for the editors, total }
            'tabs' => Submission::stageCounts(),
            'stages' => [
                'selection' => $year()->where('status', SubmissionStatus::Submitted)->count(),
                // Sent back for revision, not returned yet (the author's turn), shown with the "decide on revisions" stage
                'revising' => $year()->whereIn('status', [SubmissionStatus::Revision, SubmissionStatus::ReviewRevision])->count(),
                'cameraReady' => $year()->where('status', SubmissionStatus::Accepted)->count(),
                // Camera-ready files waiting for the editors' check
                'cameraReadyToCheck' => $year()->where('status', SubmissionStatus::CameraReady)->whereNull('camera_ready_approved_at')->count(),
                // Can present but has no session yet
                'scheduling' => $year()->schedulable()->whereNull('presentation_session_id')->count(),
            ],
            'schedule' => [
                'opens' => Setting::get('submission_opens'),
                'deadline' => Setting::get('submission_deadline'),
                'journalDeadline' => Setting::get('journal_deadline'),
                'journalAnswerDays' => Submission::journalAnswerDays(),
                'slidesCutoffDays' => Setting::get('slides_cutoff_days'),
            ],
        ];
    }
}
