<?php

namespace Tests\Feature;

use App\Models\MealOption;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Sign-up meal + companion, "My details" and the Settings meal list (inside a rolled-back transaction). */
class MealCompanionTest extends TestCase
{
    use DatabaseTransactions;

    private function signUp(array $fields): \Illuminate\Testing\TestResponse
    {
        return $this->post('/register', [
            'email' => uniqid('meal').'@smoke.test',
            'name' => 'Meal Tester',
            'affiliation' => 'msu',
            'participation' => 'attend_submit',
            'pdpa' => true,
            ...$fields,
        ]);
    }

    public function test_sign_up_saves_meal_and_companion(): void
    {
        Mail::fake();

        $this->signUp(['meal' => 'halal', 'companion' => true, 'companion_name' => ' Somchai ', 'companion_meal' => 'other', 'companion_meal_other' => 'No peanuts'])
            ->assertSessionHasNoErrors();

        $user = User::where('name', 'Meal Tester')->latest('id')->first();
        $this->assertSame(['halal', null, 'Somchai', 'other', 'No peanuts'], array_values($user->only(User::MEAL_FIELDS)));
    }

    public function test_sign_up_requires_meal_and_companion_details(): void
    {
        Mail::fake();

        $this->signUp([])->assertSessionHasErrors(['meal' => 'validation.required']);
        $this->signUp(['meal' => 'other'])->assertSessionHasErrors('meal_other');
        $this->signUp(['meal' => 'regular', 'companion' => true])->assertSessionHasErrors(['companion_name', 'companion_meal']);
        $this->signUp(['meal' => 'pizza'])->assertSessionHasErrors('meal');
    }

    public function test_without_the_tick_companion_fields_are_dropped(): void
    {
        Mail::fake();

        $this->signUp(['meal' => 'regular', 'meal_other' => 'ignored', 'companion' => false, 'companion_name' => 'Ghost'])->assertSessionHasNoErrors();

        $user = User::where('name', 'Meal Tester')->latest('id')->first();
        $this->assertNull($user->meal_other);
        $this->assertNull($user->companion_name);
    }

    public function test_my_details_updates_and_removes_the_companion(): void
    {
        $user = User::create(['name' => 'Meal Me', 'email' => uniqid('me').'@smoke.test', 'password' => 'x', 'role' => 'user', 'affiliation' => 'msu', 'participation' => 'attend_submit', 'companion_name' => 'Old', 'companion_meal' => 'regular']);

        $this->actingAs($user)->put('/my-details', ['name' => 'Meal Me', 'affiliation' => 'msu', 'meal' => 'jay', 'companion' => false])
            ->assertSessionHasNoErrors();

        $this->assertSame(['jay', null, null, null, null], array_values($user->fresh()->only(User::MEAL_FIELDS)));
    }

    public function test_a_chosen_meal_cannot_be_deleted(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $meal = MealOption::create(['code' => 'smoke_meal', 'name_en' => 'Smoke', 'name_th' => 'ทดสอบ', 'sort_order' => 99]);
        User::create(['name' => 'Eater', 'email' => uniqid('eat').'@smoke.test', 'password' => 'x', 'role' => 'user', 'affiliation' => 'msu', 'participation' => 'attend_submit', 'companion_meal' => 'smoke_meal', 'companion_name' => 'C']);

        $this->actingAs($admin)->delete("/admin/settings/meals/{$meal->id}")->assertForbidden();

        $this->actingAs($admin)->post('/admin/settings/meals', ['name_en' => 'Gluten free', 'name_th' => 'ไม่ทานกลูเตน'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('meal_options', ['code' => 'gluten_free']);
    }
}
