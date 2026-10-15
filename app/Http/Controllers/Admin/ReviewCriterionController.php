<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReviewCriterion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Review criteria (Settings › Review criteria, admin only): add, rename, reorder, delete.
 * Reviewers score every criterion from 1 to 5; a criterion that already has scores cannot be deleted.
 */
class ReviewCriterionController extends Controller
{
    private const MESSAGES = ['required' => 'validation.required', 'max' => 'validation.too_long'];

    public function store(Request $request): RedirectResponse
    {
        ReviewCriterion::create([
            ...$this->validated($request),
            'sort_order' => (int) ReviewCriterion::max('sort_order') + 1,
        ]);

        return back()->with('success', 'admin.reviewCriteria.created');
    }

    public function update(Request $request, ReviewCriterion $reviewCriterion): RedirectResponse
    {
        $reviewCriterion->update($this->validated($request));

        return back()->with('success', 'admin.accounts.saved');
    }

    /** Move one place up or down (the order on the reviewer's form). */
    public function move(Request $request, ReviewCriterion $reviewCriterion): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $ids = ReviewCriterion::ordered()->pluck('id')->all();
        $from = array_search($reviewCriterion->id, $ids, true);
        $to = $direction === 'up' ? $from - 1 : $from + 1;

        if (isset($ids[$to])) {
            [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
            DB::transaction(fn () => collect($ids)->each(fn ($id, $i) => ReviewCriterion::whereKey($id)->update(['sort_order' => $i + 1])));
        }

        return back();
    }

    /** Only criteria nobody has scored yet, so sent evaluations keep their scores. */
    public function destroy(ReviewCriterion $reviewCriterion): RedirectResponse
    {
        abort_if($reviewCriterion->scores()->exists(), 403);

        $reviewCriterion->delete();

        return back()->with('success', 'admin.reviewCriteria.deleted');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name_th' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'description_th' => ['nullable', 'string', 'max:500'],
            'description_en' => ['nullable', 'string', 'max:500'],
        ], self::MESSAGES);
    }
}
