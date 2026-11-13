<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\EmailLog;
use App\Models\Journal;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** "Remind the author" from a paper's row (2026-11-13; rolled-back transaction, no real email). */
class RemindAuthorTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $role): User
    {
        return User::create(['name' => "Remind {$role}", 'email' => uniqid($role).'@smoke.test', 'password' => 'x', 'role' => $role, 'affiliation' => 'msu', 'participation' => 'attend_submit']);
    }

    private function paper(User $author, array $state): Submission
    {
        $paper = $author->submissions()->create([
            'track_id' => Track::first()->id, 'title_th' => 'เตือน', 'title_en' => 'Remind', 'abstract_th' => 'a', 'abstract_en' => 'a',
            'keywords_th' => 'k', 'keywords_en' => 'k', 'file_path' => 'x', 'file_name' => 'x.pdf', 'file_size' => 1,
        ]);
        $paper->forceFill($state)->save();

        return $paper;
    }

    public function test_editors_remind_one_author(): void
    {
        Mail::fake();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $editor = $this->user('editor');
        $journalRole = $this->user('journal');
        $author = $this->user('user');

        $revision = $this->paper($author, ['status' => SubmissionStatus::ReviewRevision, 'review_round' => 1, 'revision_due_date' => today()->subDays(3)]);
        $camera = $this->paper($author, ['status' => SubmissionStatus::Accepted, 'review_round' => 1]);
        $offer = $this->paper($author, ['status' => SubmissionStatus::JournalSelected, 'journal_id' => Journal::first()->id, 'journal_answer_due' => today()->subDay()]);
        $screening = $this->paper($author, ['status' => SubmissionStatus::Screening]);

        // What each paper waits for
        $this->assertSame(['revision', 'cameraReady', 'journal', null], [$revision->authorReminderTask(), $camera->authorReminderTask(), $offer->authorReminderTask(), $screening->authorReminderTask()]);

        // Editors remind each; one email each, with the matching template, and the row shows when
        foreach ([[$revision, 'revision_reminder'], [$camera, 'camera_ready_reminder'], [$offer, 'journal_offer_reminder']] as [$paper, $kind]) {
            $this->actingAs($editor)->post("/admin/submissions/{$paper->id}/remind-author")->assertSessionHas('success');
            $this->assertNotNull($paper->fresh()->author_reminded_at);
            $this->assertTrue(EmailLog::where('submission_id', $paper->id)->where('kind', $kind)->exists(), $kind);
        }
        $this->assertSame($editor->id, $revision->fresh()->author_reminded_by);
        $this->assertTrue(\App\Models\UserNotification::where('user_id', $author->id)->where('kind', 'revision_reminder')->exists());

        // Nothing to remind about; the author and the journal role (except for an offer) cannot
        $this->actingAs($editor)->post("/admin/submissions/{$screening->id}/remind-author")->assertForbidden();
        $this->actingAs($author)->post("/admin/submissions/{$camera->id}/remind-author")->assertForbidden();
        $this->actingAs($journalRole)->post("/admin/submissions/{$revision->id}/remind-author")->assertForbidden();
        $this->actingAs($journalRole)->post("/admin/submissions/{$offer->id}/remind-author")->assertSessionHas('success');

        // The editor sees the email before sending: recipient, subject, Thai and English (English title and date)
        $this->actingAs($editor)->getJson("/admin/submissions/{$revision->id}/remind-author/preview")->assertOk()
            ->assertJsonPath('to.email', $author->email)
            ->assertJson(fn ($json) => $json->where('subject', fn ($s) => str_contains($s, $revision->code))
                ->where('bodyEn', fn ($s) => str_contains($s, '"Remind"') && str_contains($s, today()->subDays(3)->format('F')))
                ->etc());
        $this->actingAs($author)->getJson("/admin/submissions/{$revision->id}/remind-author/preview")->assertForbidden();

        // Editors' decisions show their email (with the comment typed) before confirming
        $this->actingAs($editor)->getJson("/admin/submissions/{$screening->id}/mail-preview?step=screen&choice=revise&comment=".urlencode('แก้บทคัดย่อ'))->assertOk()
            ->assertJson(fn ($json) => $json->where('bodyTh', fn ($s) => str_contains($s, 'แก้บทคัดย่อ'))->etc());
        $this->actingAs($editor)->getJson("/admin/submissions/{$screening->id}/mail-preview?step=decide&choice=revise&due=".today()->addWeek()->toDateString())->assertOk()
            ->assertJson(fn ($json) => $json->where('bodyEn', fn ($s) => str_contains($s, today()->addWeek()->format('j F Y')))->etc());
        $this->actingAs($editor)->getJson("/admin/submissions/{$camera->id}/mail-preview?step=decide&choice=accept&use_latest=1")->assertOk()
            ->assertJson(fn ($json) => $json->where('template', 'decision_accept_final')->etc());
        $this->actingAs($author)->getJson("/admin/submissions/{$camera->id}/mail-preview?step=check&choice=approve")->assertRedirect(); // no Submissions module

        // Group reminders show how many and the first email before sending
        $this->actingAs($editor)->getJson('/admin/selection/remind-offers/preview')->assertOk()
            ->assertJson(fn ($json) => $json->where('count', fn ($n) => $n >= 1)->has('preview.subject')->etc());

        // "Close" a revision never sent: the reason is required, shown in the email preview, then the paper is not accepted
        $this->actingAs($editor)->getJson("/admin/submissions/{$revision->id}/close-revision/preview?comment=".urlencode('ไม่ได้รับตามกำหนด'))->assertOk()
            ->assertJson(fn ($json) => $json->where('bodyTh', fn ($s) => str_contains($s, 'ไม่ได้รับตามกำหนด'))->etc());
        $this->actingAs($editor)->post("/admin/submissions/{$revision->id}/close-revision", ['comment' => ''])->assertSessionHasErrors('comment');
        $this->actingAs($author)->post("/admin/submissions/{$revision->id}/close-revision", ['comment' => 'x'])->assertRedirect();
        $this->assertSame(SubmissionStatus::ReviewRevision, $revision->fresh()->status);
        $this->actingAs($editor)->post("/admin/submissions/{$revision->id}/close-revision", ['comment' => 'ไม่ได้รับตามกำหนด'])->assertSessionHas('success');
        $this->assertSame(SubmissionStatus::ReviewRejected, $revision->fresh()->status);
        $this->assertFalse($revision->fresh()->canBeEditedByAuthor());
        $this->assertTrue(EmailLog::where('submission_id', $revision->id)->where('kind', 'revision_closed')->exists());
        $this->actingAs($editor)->post("/admin/submissions/{$revision->id}/close-revision", ['comment' => 'again'])->assertForbidden();

        // Submissions opens on the first tab with the editors' move, else "all"; an explicit tab is kept
        $first = collect(Submission::STAGES)->first(fn ($key) => Submission::stageCounts()[$key]['count'] > 0) ?? 'all';
        $this->actingAs($editor)->get('/admin/submissions')->assertOk()->assertInertia(fn ($page) => $page->where('filters.stage', $first));
        $this->actingAs($editor)->get('/admin/submissions?stage=all')->assertOk()->assertInertia(fn ($page) => $page->where('filters.stage', 'all'));
        $this->actingAs($editor)->get('/admin/submissions?stage=decide')->assertOk()->assertInertia(fn ($page) => $page->where('filters.stage', 'decide'));

        // The rows carry what the button needs
        $this->actingAs($editor)->get("/admin/submissions/{$camera->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('submission.remindTask', 'cameraReady')->whereNot('submission.authorRemindedAt', null));
    }
}
