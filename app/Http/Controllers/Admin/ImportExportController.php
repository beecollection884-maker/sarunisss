<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CsvImportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportExportController extends Controller
{
    public function __construct(
        protected CsvImportExportService $csvImportExportService,
    ) {
    }

    public function export(Request $request, string $dataset, string $format = 'csv'): StreamedResponse|Response
    {
        return $this->csvImportExportService->export($dataset, $format, $request->query());
    }

    public function template(string $type): StreamedResponse
    {
        return $this->csvImportExportService->template($type);
    }

    public function import(Request $request, string $type): JsonResponse|RedirectResponse
    {
        $payload = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
            'type' => ['nullable', Rule::in(['siswa', 'guru', 'jadwal', 'catatan-siswa'])],
        ]);

        $summary = match ($type) {
            'siswa' => $this->csvImportExportService->importStudents($payload['file']),
            'guru' => $this->csvImportExportService->importTeachers($payload['file']),
            'jadwal' => $this->csvImportExportService->importSchedules($payload['file']),
            'catatan-siswa' => $this->csvImportExportService->importStudentNotes($payload['file']),
            default => abort(404, 'Tipe import tidak ditemukan.'),
        };

        $response = [
            'message' => 'Import selesai.',
            'created' => $summary['created'],
            'updated' => $summary['updated'],
            'failed' => $summary['failed'],
            'errors' => $summary['errors'],
        ];

        if ($request->expectsJson()) {
            $importedRows = $summary['created'] + $summary['updated'];
            $status = $importedRows === 0 && $summary['failed'] > 0 ? 422 : 200;

            return response()->json($response, $status);
        }

        return back()->with('import_status', sprintf(
            'Import selesai: %s dibuat, %s diperbarui, %s gagal.',
            $summary['created'],
            $summary['updated'],
            $summary['failed'],
        ));
    }
}
