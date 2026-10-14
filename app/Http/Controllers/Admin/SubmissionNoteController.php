<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionAction;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\SubmissionNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Internal notes on a paper (admin and editors): what was agreed by phone, who is following up, ...
 * Anyone on the team may pin a note; only the writer may delete it, within SubmissionNote::DELETE_WITHIN minutes.
 */
class SubmissionNoteController extends Controller
{
    public function store(Request $request, Submission $submission): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']], ['required' => 'validation.required', 'max' => 'validation.too_long']);

        $note = $submission->notes()->make(['body' => trim($data['body'])]);
        $note->user_id = $request->user()->id;
        $note->save();
        $submission->log(SubmissionAction::NoteAdded, $request->user());

        return back()->with('success', 'notes.added');
    }

    public function pin(Submission $submission, SubmissionNote $note): RedirectResponse
    {
        abort_unless($note->submission_id === $submission->id, 404);
        $note->update(['pinned' => ! $note->pinned]);

        return back();
    }

    public function destroy(Request $request, Submission $submission, SubmissionNote $note): RedirectResponse
    {
        abort_unless($note->submission_id === $submission->id, 404);
        abort_unless($note->canBeDeletedBy($request->user()), 403);
        $note->delete();

        return back()->with('success', 'notes.deleted');
    }
}
