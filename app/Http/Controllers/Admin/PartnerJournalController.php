<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Partner journals (Settings › วารสาร, admin only): add, edit, reorder, switch off and delete. A journal that papers
 * were offered to cannot be deleted; switch it off instead (it is then no longer offered).
 */
class PartnerJournalController extends Controller
{
    private const MESSAGES = ['required' => 'validation.required', 'max' => 'validation.too_long', 'url' => 'validation.url'];

    public function store(Request $request): RedirectResponse
    {
        Journal::create([...$this->validated($request), 'sort_order' => (int) Journal::max('sort_order') + 1]);

        return back()->with('success', 'admin.journals.created');
    }

    public function update(Request $request, Journal $journal): RedirectResponse
    {
        $journal->update($this->validated($request));

        return back()->with('success', 'admin.accounts.saved');
    }

    public function move(Request $request, Journal $journal): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $ids = Journal::ordered()->pluck('id')->all();
        $from = array_search($journal->id, $ids, true);
        $to = $direction === 'up' ? $from - 1 : $from + 1;

        if (isset($ids[$to])) {
            [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
            DB::transaction(fn () => collect($ids)->each(fn ($id, $i) => Journal::whereKey($id)->update(['sort_order' => $i + 1])));
        }

        return back();
    }

    public function destroy(Journal $journal): RedirectResponse
    {
        abort_if($journal->submissions()->exists(), 403);

        $journal->delete();

        return back()->with('success', 'admin.journals.deleted');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:500'],
            'publisher' => ['nullable', 'string', 'max:500'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_position' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'active' => ['boolean'],
        ], self::MESSAGES);

        return [...$data, 'active' => (bool) ($data['active'] ?? true)];
    }
}
