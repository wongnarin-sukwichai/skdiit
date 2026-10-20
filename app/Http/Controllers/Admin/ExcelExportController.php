<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Excel download of the rows a list page shows (registrants, reports, activity log).
 * The page sends the rows it shows, already filtered and in the viewer's language.
 * Allowed for anyone with staff access to one of those pages.
 */
class ExcelExportController extends Controller
{
    /** Pages that export through here; the user needs one of them. */
    private const MODULES = ['registrants', 'reports', 'activity-log'];

    public function __invoke(Request $request): StreamedResponse
    {
        // Staff access only ('own' is an author's or reviewer's own records, not a list to export)
        abort_unless(collect(self::MODULES)->contains(fn (string $module) => in_array($request->user()->moduleAccess($module), ['full', 'view'], true)), 403);

        $data = $request->validate([
            'headers' => ['required', 'array', 'max:20'],
            'headers.*' => ['nullable', 'string', 'max:100'],
            'rows' => ['present', 'array', 'max:10000'],
            'rows.*' => ['array', 'max:20'],
            'rows.*.*' => ['nullable', 'string', 'max:1000'],
            // e.g. registrants, report-papers, activity-log
            'name' => ['nullable', 'alpha_dash', 'max:40'],
        ]);

        return response()->streamDownload(function () use ($data) {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues($data['headers'], (new Style)->setFontBold()));

            foreach ($data['rows'] as $row) {
                $writer->addRow(Row::fromValues($row));
            }

            $writer->close();
        }, 'skdiit2027-'.($data['name'] ?? 'export').'-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
