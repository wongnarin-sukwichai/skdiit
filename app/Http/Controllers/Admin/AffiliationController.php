<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Affiliations (Settings › Affiliations, admin only): add, rename, reorder, delete, and turn a name
 * registrants typed under "Other" into a list entry. The list is shared with every page (sign-up form).
 */
class AffiliationController extends Controller
{
    private const MESSAGES = ['required' => 'validation.required', 'max' => 'validation.too_long'];

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Affiliation::create([
            ...$data,
            'code' => Affiliation::newCode($data['name_en']),
            'sort_order' => (int) Affiliation::max('sort_order') + 1,
        ]);

        return back()->with('success', 'admin.affiliations.created');
    }

    public function update(Request $request, Affiliation $affiliation): RedirectResponse
    {
        $affiliation->update($this->validated($request));

        return back()->with('success', 'admin.accounts.saved');
    }

    public function move(Request $request, Affiliation $affiliation): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $ids = Affiliation::ordered()->pluck('id')->all();
        $from = array_search($affiliation->id, $ids, true);
        $to = $direction === 'up' ? $from - 1 : $from + 1;

        if (isset($ids[$to])) {
            [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
            DB::transaction(fn () => collect($ids)->each(fn ($id, $i) => Affiliation::whereKey($id)->update(['sort_order' => $i + 1])));
        }

        return back();
    }

    /** Only affiliations nobody uses can be deleted, so no account loses its affiliation. */
    public function destroy(Affiliation $affiliation): RedirectResponse
    {
        abort_if($affiliation->users()->exists(), 403);

        $affiliation->delete();

        return back()->with('success', 'admin.affiliations.deleted');
    }

    /**
     * A name registrants typed under "Other" becomes a list entry: everyone who typed exactly that name
     * moves to it, so the list and the reports count them together.
     */
    public function adopt(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'typed' => ['required', 'string', 'max:255'],
            ...$this->rules(),
        ], self::MESSAGES);

        DB::transaction(function () use ($data) {
            $affiliation = Affiliation::create([
                'code' => Affiliation::newCode($data['name_en']),
                'name_en' => $data['name_en'],
                'name_th' => $data['name_th'],
                'sort_order' => (int) Affiliation::max('sort_order') + 1,
            ]);
            User::where('affiliation', Affiliation::OTHER)->where('affiliation_other', $data['typed'])
                ->update(['affiliation' => $affiliation->code, 'affiliation_other' => null]);
        });

        return back()->with('success', 'admin.affiliations.adopted');
    }

    private function rules(): array
    {
        return [
            'name_th' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate($this->rules(), self::MESSAGES);
    }
}
