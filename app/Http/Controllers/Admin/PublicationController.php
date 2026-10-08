<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionAction;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * After acceptance (editors and admin): Option B journal coordination and presentation slots.
 */
class PublicationController extends Controller
{
    private const MESSAGES = [
        'required' => 'validation.required',
        'max' => 'validation.too_long',
        'date_format' => 'validation.date',
    ];

    /**
     * Excel list of Option B papers still waiting for a journal, for the editors to send to the journals.
     * Files are downloaded from each paper's page (they need a signed-in editor).
     */
    public function exportOptionB(): StreamedResponse
    {
        $submissions = Submission::where('status', SubmissionStatus::OptionB)
            ->with('track', 'authors', 'user')
            ->orderBy('code')
            ->get();

        return response()->streamDownload(function () use ($submissions) {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues([
                'Code', 'Title (TH)', 'Title (EN)', 'Track', 'Authors', 'Affiliations', 'Contact name', 'Contact email',
                'Keywords (TH)', 'Keywords (EN)', 'Abstract (EN)', 'File name', 'File link (sign in required)', 'Journal',
            ], (new Style)->setFontBold()));

            foreach ($submissions as $submission) {
                $writer->addRow(Row::fromValues([
                    $submission->code,
                    $submission->title_th,
                    $submission->title_en,
                    $submission->track->name_en,
                    $submission->authors->pluck('name')->implode(', '),
                    $submission->authors->pluck('affiliation')->unique()->implode(', '),
                    $submission->user?->name,
                    $submission->user?->email,
                    $submission->keywords_th,
                    $submission->keywords_en,
                    $submission->abstract_en,
                    $submission->file_name,
                    url("/admin/submissions/{$submission->id}/file"),
                    $submission->journal_name,
                ]));
            }

            $writer->close();
        }, 'skdiit2027-option-b-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * The journal's answer for an Option B paper. Accepted: done (pay + present). Rejected: the paper moves
     * to Option A (Proceeding), as in the workflow, and the author uploads a camera-ready file.
     */
    public function journalResult(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->status === SubmissionStatus::OptionB, 403);

        $data = $request->validate([
            'journal' => ['required', 'string', 'max:255'],
            'result' => ['required', Rule::in(['accepted', 'rejected'])],
            'comment' => ['nullable', 'string', 'max:5000'],
        ], self::MESSAGES);

        $from = $submission->status;
        $submission->journal_name = $data['journal'];
        if ($data['result'] === 'accepted') {
            $submission->status = SubmissionStatus::JournalAccepted;
        } else {
            $submission->status = SubmissionStatus::OptionA;
            $submission->publish_option = 'a';
        }
        $submission->save();

        $submission->log(SubmissionAction::JournalResult, $request->user(), $from, $data['comment'] ?? null, [
            'journal' => $data['journal'],
            'result' => $data['result'],
        ]);

        return back()->with('success', 'publication.journalSaved');
    }

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
