<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SiteClosure;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Settings › ปิดระบบ: only admin gets in while the system is closed (inside a rolled-back transaction). */
class SiteClosureTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $role): User
    {
        return User::create(['name' => "Close {$role}", 'email' => uniqid($role).'@smoke.test', 'password' => 'secret1', 'role' => $role, 'affiliation' => 'msu', 'participation' => 'attend_submit']);
    }

    public function test_closed_system_lets_only_admin_in(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $admin = $this->user('admin');
        $editor = $this->user('editor');

        $this->actingAs($editor)->put('/admin/settings/closure', ['closed' => true])->assertRedirect(); // editors have no Settings
        $this->assertFalse(SiteClosure::isClosed());
        $this->actingAs($admin)->put('/admin/settings/closure', ['closed' => true, 'message' => ['th' => 'จบแล้ว', 'en' => '']])->assertSessionHasNoErrors();
        $this->assertTrue(SiteClosure::isClosed());
        $this->assertTrue(\App\Models\ActivityLog::where('action', 'site_closed')->where('user_id', $admin->id)->exists());

        // Admin keeps working (and sees the banner); everyone else gets the closed page, signed in or not
        $this->actingAs($admin)->get('/admin')->assertOk()->assertInertia(fn ($page) => $page->component('Admin/Dashboard')->where('siteClosed', true));
        $this->actingAs($editor)->get('/admin')->assertOk()->assertInertia(fn ($page) => $page->component('Closed')->where('message.th', 'จบแล้ว'));
        auth()->logout();
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('Closed'));

        // Only admin may sign in
        $this->post('/login', ['email' => $editor->email, 'password' => 'secret1'])->assertSessionHasErrors(['email' => 'auth.siteClosed']);
        $this->assertGuest();
        $this->post('/login', ['email' => $admin->email, 'password' => 'secret1'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($admin);

        // Open again: everyone back
        $this->actingAs($admin)->put('/admin/settings/closure', ['closed' => false, 'message' => ['th' => 'จบแล้ว', 'en' => '']])->assertSessionHasNoErrors();
        $this->actingAs($editor)->get('/admin')->assertOk()->assertInertia(fn ($page) => $page->component('Admin/Dashboard'));
    }
}
