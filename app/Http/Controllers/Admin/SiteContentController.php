<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SiteDocument;
use App\Support\SiteContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Conference information (Settings › Conference) and public-site content (Settings › Website content):
 * the home headline, About, Contact, the privacy policy, the downloadable documents, and the important dates and day
 * programme (Settings › Schedule). Admin only (module settings).
 */
class SiteContentController extends Controller
{
    private const MESSAGES = [
        'required' => 'validation.required',
        'max' => 'validation.too_long',
        'email' => 'validation.email',
        'date_format' => 'validation.date',
        'file.mimes' => 'validation.file_type',
        'file.max' => 'validation.file_size',
        'file.uploaded' => 'validation.file_size',
    ];

    /** Both languages of one text: required unless $optional. */
    private static function bilingual(string $field, int $max, bool $optional = false): array
    {
        $rule = [$optional ? 'nullable' : 'required', 'string', "max:{$max}"];

        return ["{$field}.th" => $rule, "{$field}.en" => $rule];
    }

    public function updateConference(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shortName' => ['required', 'string', 'max:40'],
            ...self::bilingual('fullName', 255),
            'date' => ['required', 'date_format:Y-m-d'],
            ...self::bilingual('venue', 255),
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
        ], self::MESSAGES);

        SiteContent::put('conference', $data);

        return back()->with('success', 'admin.settings.saved');
    }

    /** One page's texts: home | about | contact. */
    public function updatePage(Request $request, string $page): RedirectResponse
    {
        abort_unless(in_array($page, SiteContent::PAGES, true), 404);

        $rules = match ($page) {
            'home' => self::bilingual('title', 255),
            'about' => [
                ...self::bilingual('lead', 2000),
                'objectives' => ['present', 'array', 'max:20'],
                'objectives.*.th' => ['required', 'string', 'max:500'],
                'objectives.*.en' => ['required', 'string', 'max:500'],
            ],
            'contact' => [...self::bilingual('lead', 2000), ...self::bilingual('address', 500), ...self::bilingual('hours', 255)],
            'privacy' => self::bilingual('body', 20000),
        };
        $data = $request->validate($rules, self::MESSAGES);
        if ($page === 'about') {
            $data['objectives'] = array_values($data['objectives']);
        }

        SiteContent::put($page, $data);

        return back()->with('success', 'admin.settings.saved');
    }

    /** Important dates (kept in date order) and the conference-day programme (kept as entered). */
    public function updateSchedule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'dates' => ['present', 'array', 'max:30'],
            'dates.*.date' => ['required', 'date_format:Y-m-d'],
            'dates.*.label.th' => ['required', 'string', 'max:255'],
            'dates.*.label.en' => ['required', 'string', 'max:255'],
            'program' => ['present', 'array', 'max:40'],
            'program.*.time' => ['required', 'string', 'max:40'],
            'program.*.label.th' => ['required', 'string', 'max:255'],
            'program.*.label.en' => ['required', 'string', 'max:255'],
        ], self::MESSAGES);

        SiteContent::put('dates', collect($data['dates'])->sortBy('date')->values()->all());
        SiteContent::put('program', array_values($data['program']));

        return back()->with('success', 'admin.settings.saved');
    }

    // ---- Downloadable documents ----

    public function storeDocument(Request $request): RedirectResponse
    {
        $data = $this->validatedDocument($request, fileRequired: true);
        $file = $request->file('file');

        SiteDocument::create([
            ...$data,
            'path' => $file->store('site-documents'),
            'file_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'sort_order' => (int) SiteDocument::max('sort_order') + 1,
        ]);

        return back()->with('success', 'admin.website.documentAdded');
    }

    /** Rename, and optionally replace the file (the old file is removed: these are public templates, not records). */
    public function updateDocument(Request $request, SiteDocument $siteDocument): RedirectResponse
    {
        $data = $this->validatedDocument($request, fileRequired: false);
        $old = null;
        if ($file = $request->file('file')) {
            $old = $siteDocument->path;
            $data += ['path' => $file->store('site-documents'), 'file_name' => $file->getClientOriginalName(), 'size' => $file->getSize()];
        }
        $siteDocument->update($data);
        if ($old) {
            Storage::delete($old);
        }

        return back()->with('success', 'admin.settings.saved');
    }

    public function moveDocument(Request $request, SiteDocument $siteDocument): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $ids = SiteDocument::ordered()->pluck('id')->all();
        $from = array_search($siteDocument->id, $ids, true);
        $to = $direction === 'up' ? $from - 1 : $from + 1;
        if (isset($ids[$to])) {
            [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
            DB::transaction(fn () => collect($ids)->each(fn ($id, $i) => SiteDocument::whereKey($id)->update(['sort_order' => $i + 1])));
        }

        return back();
    }

    public function destroyDocument(SiteDocument $siteDocument): RedirectResponse
    {
        Storage::delete($siteDocument->path);
        $siteDocument->delete();

        return back()->with('success', 'admin.website.documentDeleted');
    }

    /** Public download (anyone can open the Documents page). */
    public function download(SiteDocument $siteDocument): StreamedResponse
    {
        return Storage::download($siteDocument->path, $siteDocument->file_name);
    }

    private function validatedDocument(Request $request, bool $fileRequired): array
    {
        return $request->validate([
            'name_th' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'file' => [$fileRequired ? 'required' : 'nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip', 'max:'.((int) Setting::get('upload_max_mb') * 1024)],
        ], self::MESSAGES);
    }
}
