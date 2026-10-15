<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\SubmissionAuthor;
use App\Models\Track;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Article archive (admin): finished papers of earlier conference years - accepted by the journal, or published in
 * the Proceeding (camera-ready received). Papers join it when admin starts a new conference year.
 */
class ArticleController extends Controller
{
    public function index(): Response
    {
        $articles = Submission::archived()->with('authors', 'track')->orderByDesc('year')->orderBy('code')->get()
            ->map(function (Submission $paper) {
                $journal = $paper->status === SubmissionStatus::JournalAccepted;

                return [
                    'id' => $paper->id,
                    'code' => $paper->code,
                    'year' => $paper->year,
                    'title' => ['en' => $paper->title_en, 'th' => $paper->title_th],
                    // Authors' names and affiliations are typed once, so both languages show the same text
                    'authors' => $paper->authors->map(fn (SubmissionAuthor $a) => [
                        'name' => ['en' => $a->name, 'th' => $a->name],
                        'affiliation' => ['en' => $a->affiliation, 'th' => $a->affiliation],
                    ])->values(),
                    'track' => $paper->track->toClient(),
                    'type' => $journal ? 'journal' : 'proceeding',
                    'keywords' => ['en' => $paper->keywords_en, 'th' => $paper->keywords_th],
                    'abstract' => ['en' => $paper->abstract_en, 'th' => $paper->abstract_th],
                    // The Proceeding keeps the camera-ready file; a journal paper keeps the last manuscript
                    'file' => $journal || ! $paper->camera_ready_path
                        ? ['name' => $paper->file_name, 'size' => $paper->file_size, 'url' => "/admin/submissions/{$paper->id}/file"]
                        : ['name' => $paper->camera_ready_name, 'size' => $paper->camera_ready_size, 'url' => "/admin/submissions/{$paper->id}/camera-ready"],
                ];
            });

        return Inertia::render('Admin/Articles', [
            'articles' => $articles,
            'tracks' => Track::ordered()->get()->map->toClient(),
            'currentYear' => Submission::conferenceYear(),
        ]);
    }
}
