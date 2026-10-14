<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicTitle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Academic titles (Settings › Academic titles, admin only): add, rename, reorder, delete.
 * The list is shared with every backend page (HandleInertiaRequests: academicTitles).
 */
class AcademicTitleController extends Controller
{
    private const MESSAGES = ['required' => 'validation.required', 'max' => 'validation.too_long'];

    public function store(Request $request): RedirectResponse
    {
        AcademicTitle::create([
            ...$this->validated($request),
            'sort_order' => (int) AcademicTitle::max('sort_order') + 1,
        ]);

        return back()->with('success', 'admin.academicTitles.created');
    }

    public function update(Request $request, AcademicTitle $academicTitle): RedirectResponse
    {
        $academicTitle->update($this->validated($request));

        return back()->with('success', 'admin.accounts.saved');
    }

    /** Move one place up or down in the list (the order of the pickers). */
    public function move(Request $request, AcademicTitle $academicTitle): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $ids = AcademicTitle::ordered()->pluck('id')->all();
        $from = array_search($academicTitle->id, $ids, true);
        $to = $direction === 'up' ? $from - 1 : $from + 1;

        if (isset($ids[$to])) {
            [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
            DB::transaction(fn () => collect($ids)->each(fn ($id, $i) => AcademicTitle::whereKey($id)->update(['sort_order' => $i + 1])));
        }

        return back();
    }

    /** Only titles nobody uses can be deleted, so no account silently loses its title. */
    public function destroy(AcademicTitle $academicTitle): RedirectResponse
    {
        abort_if($academicTitle->users()->exists(), 403);

        $academicTitle->delete();

        return back()->with('success', 'admin.academicTitles.deleted');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'short_th' => ['required', 'string', 'max:50'],
            'short_en' => ['required', 'string', 'max:50'],
            'name_th' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
        ], self::MESSAGES);
    }
}
