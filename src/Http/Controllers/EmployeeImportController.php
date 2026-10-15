<?php

namespace ME\Hr\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use ME\Hr\Services\EmployeeExcelService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class EmployeeImportController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly EmployeeExcelService $excel)
    {
    }

    public function create(): View
    {
        $this->authorize('hr_employee.import');

        return view('hr::employees.import');
    }

    public function preview(Request $request): JsonResponse
    {
        $this->authorize('hr_employee.import');
        $this->extendLimits();

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls']]);

        return response()->json($this->excel->preview($request->file('file')));
    }

    /**
     * Saves the previewed rows (including any inline edits) when the page sends them,
     * otherwise re-reads the uploaded file.
     */
    public function save(Request $request): JsonResponse
    {
        $this->authorize('hr_employee.import');
        $this->extendLimits();

        if ($request->filled('rows')) {
            $rows = json_decode((string) $request->input('rows'), true);
            if (!is_array($rows)) {
                return response()->json(['error' => 'Invalid import data. Please upload the file again.', 'rows' => []]);
            }

            return response()->json($this->excel->importRows($rows));
        }

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls']]);

        return response()->json($this->excel->import($request->file('file')));
    }

    public function recheck(Request $request): JsonResponse
    {
        $this->authorize('hr_employee.import');

        $data = $request->validate(['row' => ['required']]);

        return response()->json(['row' => $data['row'], ...$this->excel->validateRow($request->input('fields', []))]);
    }

    public function template()
    {
        $this->authorize('hr_employee.import');
        $this->ensureSpreadsheetInstalled();

        return self::download($this->excel->template(), 'employee-import-template.xlsx');
    }

    public static function download(Spreadsheet $spreadsheet, string $filename)
    {
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function ensureSpreadsheetInstalled(): void
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            abort(501, 'Excel import requires the phpoffice/phpspreadsheet package. Run "composer require phpoffice/phpspreadsheet" in this application, then try again.');
        }
    }

    private function extendLimits(): void
    {
        $this->ensureSpreadsheetInstalled();
        @set_time_limit(600);
        @ini_set('memory_limit', '1024M');
    }
}
