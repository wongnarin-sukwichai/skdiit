<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Mail\TemplatedMail;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Editors take the latest reviewed PDF as the approved camera-ready file (2026-11-13; rolled-back transaction, fake disk). */
class UseLatestCameraReadyTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $role): User
    {
        return User::create(['name' => "Latest {$role}", 'email' => uniqid($role).'@smoke.test', 'password' => 'x', 'role' => $role, 'affiliation' => 'msu', 'participation' => 'attend_submit']);
    }

    private function paper(User $author, SubmissionStatus $status): Submission
    {
        Storage::put('submissions/latest-v2.pdf', '%PDF-1.4 reviewed');
        $paper = $author->submissions()->create([
            'track_id' => Track::first()->id, 'title_th' => 'ล่าสุด', 'title_en' => 'Latest', 'abstract_th' => 'a', 'abstract_en' => 'a',
            'keywords_th' => 'k', 'keywords_en' => 'k', 'file_path' => 'submissions/latest-v2.pdf', 'file_name' => 'paper-v2.pdf', 'file_size' => 17,
        ]);
        $paper->addManuscript(['file_path' => 'submissions/latest-v2.pdf', 'file_name' => 'paper-v2.pdf', 'file_size' => 17], 'review', $author);
        $paper->forceFill(['status' => $status])->save();

        return $paper;
    }

    public function test_accept_with_the_latest_file(): void
    {
        Storage::fake();
        Mail::fake();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $editor = $this->user('editor');
        $author = $this->user('user');
        $paper = $this->paper($author, SubmissionStatus::Revised);

        $this->actingAs($editor)->post("/admin/submissions/{$paper->id}/decision", ['decision' => 'accept', 'use_latest' => '1'])->assertSessionHasNoErrors();

        $paper->refresh();
        $this->assertSame(SubmissionStatus::CameraReady, $paper->status);
        $this->assertNotNull($paper->camera_ready_approved_at);
        $this->assertSame('paper-v2.pdf', $paper->camera_ready_name);
        Storage::assertExists($paper->camera_ready_path);
        $this->assertTrue($paper->canBeScheduled());
        // One email to the author ("accepted, nothing more to send"), not a second "approved" one
        Mail::assertSent(TemplatedMail::class, 1);
        $this->assertTrue(\App\Models\EmailLog::where('submission_id', $paper->id)->where('kind', 'decision_accept_final')->exists());
        $this->assertFalse(\App\Models\EmailLog::where('submission_id', $paper->id)->where('kind', 'camera_ready_approved')->exists());
    }

    public function test_use_the_latest_file_while_waiting_for_the_author(): void
    {
        Storage::fake();
        Mail::fake();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $editor = $this->user('editor');
        $author = $this->user('user');
        $paper = $this->paper($author, SubmissionStatus::Accepted);

        $this->actingAs($author)->post("/admin/submissions/{$paper->id}/camera-ready/use-latest")->assertRedirect(); // authors cannot
        $this->assertSame(SubmissionStatus::Accepted, $paper->fresh()->status);

        $this->actingAs($editor)->post("/admin/submissions/{$paper->id}/camera-ready/use-latest")->assertSessionHas('success');
        $this->assertNotNull($paper->fresh()->camera_ready_approved_at);
        $this->assertTrue($paper->events()->where('action', 'camera_ready_approved')->latest('id')->first()->meta['from_latest']);
        $this->assertTrue(\App\Models\EmailLog::where('submission_id', $paper->id)->where('kind', 'camera_ready_approved')->exists());

        // Already approved: nothing more to take
        $this->actingAs($editor)->post("/admin/submissions/{$paper->id}/camera-ready/use-latest")->assertForbidden();
    }
}
