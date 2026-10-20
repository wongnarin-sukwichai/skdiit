<?php

namespace Tests\Feature;

use App\Models\AuthorRequest;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Registrants page (real data) and requests to submit papers (inside a rolled-back transaction). */
class RegistrantRequestTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        Mail::fake();
    }

    private function user(string $role, string $participation = 'attend'): User
    {
        $user = User::create(['name' => "Test {$role}", 'email' => uniqid($role).'@registrant.test', 'password' => 'x', 'role' => $role, 'affiliation' => 'other', 'affiliation_other' => 'Somewhere', 'participation' => $participation]);
        $user->forceFill(['registered_year' => Submission::conferenceYear()])->save();

        return $user;
    }

    public function test_registrants_list_and_admin_actions(): void
    {
        $admin = $this->user('admin');
        $editor = $this->user('editor');
        $attendee = $this->user('user');

        $this->actingAs($admin)->get('/admin/registrants')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Registrants')->where('registrants', fn ($rows) => collect($rows)->contains('email', $attendee->email)));
        $this->actingAs($editor)->get('/admin/registrants')->assertOk();

        // Editors only view
        $this->actingAs($editor)->post("/admin/registrants/{$attendee->id}/suspend")->assertForbidden();

        // Admin adds a registrant: same rules as signing up, password emailed
        $this->actingAs($admin)->post('/admin/registrants', ['name' => 'New', 'email' => 'NEW@registrant.test', 'affiliation' => 'nope', 'participation' => 'attend'])
            ->assertSessionHasErrors('affiliation');
        $this->actingAs($admin)->post('/admin/registrants', ['name' => 'New', 'email' => 'NEW@registrant.test', 'affiliation' => 'other', 'affiliation_other' => 'X', 'participation' => 'attend'])
            ->assertSessionHasNoErrors();
        $created = User::where('email', 'new@registrant.test')->firstOrFail();
        $this->assertSame('user', $created->role);
        $this->assertSame(Submission::conferenceYear(), (int) $created->registered_year);

        // Switching to author by hand emails the registrant
        $this->actingAs($admin)->put("/admin/registrants/{$created->id}", ['name' => 'New', 'email' => $created->email, 'affiliation' => 'other', 'affiliation_other' => 'X', 'participation' => 'attend_submit'])
            ->assertSessionHasNoErrors();
        $this->assertSame('attend_submit', $created->fresh()->participation);

        // Suspend, make reviewer; staff accounts are not registrants
        $this->actingAs($admin)->post("/admin/registrants/{$attendee->id}/suspend")->assertSessionHasNoErrors();
        $this->assertNotNull($attendee->fresh()->suspended_at);
        $this->actingAs($admin)->post("/admin/registrants/{$created->id}/make-reviewer")->assertRedirect();
        $this->assertSame('reviewer', $created->fresh()->role);
        $this->actingAs($admin)->post("/admin/registrants/{$editor->id}/suspend")->assertNotFound();
    }

    public function test_request_to_submit_papers(): void
    {
        $admin = $this->user('admin');
        $attendee = $this->user('user');
        $author = $this->user('user', 'attend_submit');
        Setting::put('submission_deadline', now()->addMonth()->toDateString());

        // Shared with My details
        $this->actingAs($attendee)->get('/')->assertInertia(fn ($page) => $page->where('auth.user.authorRequest.canAsk', true)->where('auth.user.authorRequest.status', null));
        $this->actingAs($author)->get('/')->assertInertia(fn ($page) => $page->where('auth.user.authorRequest', null));

        // Only attend-only registrants ask, once at a time
        $this->actingAs($author)->post('/author-request')->assertForbidden();
        $this->actingAs($attendee)->post('/author-request', ['reason' => 'I have a paper'])->assertSessionHasNoErrors();
        $this->actingAs($attendee)->post('/author-request')->assertStatus(409);
        $request = AuthorRequest::where('user_id', $attendee->id)->firstOrFail();
        $this->actingAs($attendee)->get('/')->assertInertia(fn ($page) => $page->where('auth.user.authorRequest.status', 'pending')->where('auth.user.authorRequest.canAsk', false));
        $this->actingAs($admin)->get('/admin/registrants')->assertInertia(fn ($page) => $page->where('requests.0.id', $request->id)->where('requests.0.status', 'pending'));

        // Declined: the note reaches the registrant, who may ask again
        $this->actingAs($admin)->post("/admin/registrants/requests/{$request->id}/reject", ['note' => 'Not this year'])->assertSessionHasNoErrors();
        $this->assertSame('attend', $attendee->fresh()->participation);
        $this->actingAs($attendee)->get('/')->assertInertia(fn ($page) => $page->where('auth.user.authorRequest.status', 'rejected')->where('auth.user.authorRequest.note', 'Not this year')->where('auth.user.authorRequest.canAsk', true));
        $this->actingAs($admin)->post("/admin/registrants/requests/{$request->id}/approve")->assertStatus(409);

        // Asked again and approved: now an author with My submissions
        $this->actingAs($attendee)->post('/author-request')->assertSessionHasNoErrors();
        $again = AuthorRequest::where('user_id', $attendee->id)->pending()->firstOrFail();
        $this->actingAs($admin)->post("/admin/registrants/requests/{$again->id}/approve")->assertSessionHasNoErrors();
        $this->assertSame('attend_submit', $attendee->fresh()->participation);
        $this->assertSame('approved', $again->fresh()->status);
        $this->actingAs($attendee->fresh())->get('/admin/my-submissions')->assertOk();

        // No requests once submissions have closed
        $other = $this->user('user');
        Setting::put('submission_deadline', now()->subDay()->toDateString());
        $this->actingAs($other)->post('/author-request')->assertForbidden();
    }
}
