<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Registrants page (real data, inside a rolled-back transaction). The request to submit papers was removed 2026-11-11. */
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

}
