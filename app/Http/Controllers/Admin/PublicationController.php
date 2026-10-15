<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionAction;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * After acceptance (editors and admin): presentation slots. The journal's decisions live in JournalController.
 */
class PublicationController extends Controller
{
    private const MESSAGES = [
        'required' => 'validation.required',
        'max' => 'validation.too_long',
        'date_format' => 'validation.date',
    ];

    /** Set or clear the presentation date, time and room. */
    public function schedule(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->status->canBeScheduled(), 403);

        $data = $request->validate([
            'at' => ['nullable', 'date_format:Y-m-d\TH:i', 'required_with:room'],
            'room' => ['nullable', 'string', 'max:255', 'required_with:at'],
        ], [...self::MESSAGES, 'required_with' => 'validation.required']);

        $submission->presentation_at = $data['at'] ?? null;
        $submission->presentation_room = $data['room'] ?? null;
        $submission->save();

        $submission->log(SubmissionAction::PresentationScheduled, $request->user(), meta: [
            'at' => $submission->presentation_at?->toIso8601String(),
            'room' => $submission->presentation_room,
        ]);

        return back()->with('success', 'publication.scheduleSaved');
    }
}
