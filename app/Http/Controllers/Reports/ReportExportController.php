<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Jobs\ExportReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function export(Request $request, string $report): RedirectResponse
    {
        abort_unless(in_array($report, ExportReport::REPORTS, true), 404);

        $filters = $request->validate([
            'warehouse_id' => ['sometimes', 'nullable', 'integer'],
            'movement_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:50'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $fileName = 'report-export-'.tenant()->getTenantKey().'-'.$report.'-'
            .now()->format('YmdHis').'-'.Str::lower(Str::random(6)).'.csv';

        ExportReport::dispatch(
            (string) tenant()->getTenantKey(),
            (int) $request->user()->id,
            $report,
            $fileName,
            array_filter($filters, fn ($value): bool => $value !== null && $value !== ''),
        );

        return redirect()->back()
            ->with('status', __('Report export queued: :file', ['file' => $fileName]));
    }

    public function download(string $fileName): StreamedResponse
    {
        $tenantKey = (string) tenant()->getTenantKey();

        abort_unless(Str::startsWith($fileName, 'report-export-'.$tenantKey.'-'), 404);

        $path = 'report-exports/'.$fileName;

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $fileName);
    }
}
