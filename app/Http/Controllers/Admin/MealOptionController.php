<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MealOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Meal preferences (Settings › Meals, admin only): add, rename, reorder and delete.
 * The list is shared with every page (sign-up form, "My details"); "Other" is added on the client.
 */
class MealOptionController extends Controller
{
    private const MESSAGES = ['required' => 'validation.required', 'max' => 'validation.too_long'];

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        MealOption::create([
            ...$data,
            'code' => MealOption::newCode($data['name_en']),
            'sort_order' => (int) MealOption::max('sort_order') + 1,
        ]);

        return back()->with('success', 'admin.meals.created');
    }

    public function update(Request $request, MealOption $meal): RedirectResponse
    {
        $meal->update($this->validated($request));

        return back()->with('success', 'admin.accounts.saved');
    }

    public function move(Request $request, MealOption $meal): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $ids = MealOption::ordered()->pluck('id')->all();
        $from = array_search($meal->id, $ids, true);
        $to = $direction === 'up' ? $from - 1 : $from + 1;

        if (isset($ids[$to])) {
            [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
            DB::transaction(fn () => collect($ids)->each(fn ($id, $i) => MealOption::whereKey($id)->update(['sort_order' => $i + 1])));
        }

        return back();
    }

    /** Only meals nobody chose can be deleted, so no registrant or companion loses their choice. */
    public function destroy(MealOption $meal): RedirectResponse
    {
        abort_if($meal->usersCount() > 0, 403);

        $meal->delete();

        return back()->with('success', 'admin.meals.deleted');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name_th' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
        ], self::MESSAGES);
    }
}
