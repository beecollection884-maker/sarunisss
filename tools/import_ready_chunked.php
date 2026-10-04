<?php

use App\Services\CsvImportExportService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var CsvImportExportService $service */
$service = $app->make(CsvImportExportService::class);
$basePath = $app->basePath('storage/app/import-ready');
$chunkDir = $basePath . DIRECTORY_SEPARATOR . '.chunks';

if (! is_dir($chunkDir)) {
    mkdir($chunkDir, 0777, true);
}

$only = $argv[1] ?? 'all';

if ($only === 'all' || $only === 'guru') {
    runImport($service, $basePath . DIRECTORY_SEPARATOR . 'import-guru-final.csv', 'import-guru-final.csv', 'importTeachers');
}
if ($only === 'all' || $only === 'siswa') {
    runChunkedImport($service, $basePath . DIRECTORY_SEPARATOR . 'import-siswa-final.csv', 'import-siswa-final.csv', 'importStudents', $chunkDir, 40);
}
if ($only === 'all' || $only === 'jadwal') {
    runImport($service, $basePath . DIRECTORY_SEPARATOR . 'import-jadwal-final.csv', 'import-jadwal-final.csv', 'importSchedules');
}

function runImport(CsvImportExportService $service, string $path, string $filename, string $method): void
{
    if (! is_file($path)) {
        fwrite(STDERR, "File {$filename} tidak ditemukan.\n");
        exit(1);
    }

    $startedAt = microtime(true);
    $file = new UploadedFile($path, $filename, 'text/csv', null, true);
    $summary = $service->{$method}($file);

    printSummary($filename, $summary, microtime(true) - $startedAt);
}

function runChunkedImport(
    CsvImportExportService $service,
    string $path,
    string $filename,
    string $method,
    string $chunkDir,
    int $chunkSize
): void {
    if (! is_file($path)) {
        fwrite(STDERR, "File {$filename} tidak ditemukan.\n");
        exit(1);
    }

    $input = fopen($path, 'r');
    if ($input === false) {
        fwrite(STDERR, "File {$filename} tidak dapat dibaca.\n");
        exit(1);
    }

    $header = fgetcsv($input);
    if ($header === false) {
        fwrite(STDERR, "File {$filename} tidak memiliki header.\n");
        exit(1);
    }

    $chunk = [];
    $chunkNumber = 1;
    $total = ['created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];

    while (($row = fgetcsv($input)) !== false) {
        if (array_filter($row, fn ($value) => trim((string) $value) !== []) === []) {
            continue;
        }

        $chunk[] = $row;

        if (count($chunk) >= $chunkSize) {
            importChunk($service, $method, $filename, $header, $chunk, $chunkDir, $chunkNumber, $total);
            $chunk = [];
            $chunkNumber++;
        }
    }

    if ($chunk !== []) {
        importChunk($service, $method, $filename, $header, $chunk, $chunkDir, $chunkNumber, $total);
    }

    fclose($input);
    echo "TOTAL {$filename}: " . json_encode($total, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

function importChunk(
    CsvImportExportService $service,
    string $method,
    string $sourceFilename,
    array $header,
    array $rows,
    string $chunkDir,
    int $chunkNumber,
    array &$total
): void {
    $chunkFilename = pathinfo($sourceFilename, PATHINFO_FILENAME) . '-chunk-' . str_pad((string) $chunkNumber, 4, '0', STR_PAD_LEFT) . '.csv';
    $chunkPath = $chunkDir . DIRECTORY_SEPARATOR . $chunkFilename;
    $output = fopen($chunkPath, 'w');

    if ($output === false) {
        fwrite(STDERR, "Chunk {$chunkFilename} tidak dapat dibuat.\n");
        exit(1);
    }

    fputcsv($output, $header);
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);

    $startedAt = microtime(true);
    $file = new UploadedFile($chunkPath, $chunkFilename, 'text/csv', null, true);
    $summary = $service->{$method}($file);

    foreach (['created', 'updated', 'failed'] as $key) {
        $total[$key] += $summary[$key];
    }
    $total['errors'] = array_merge($total['errors'], $summary['errors']);

    printSummary($chunkFilename, $summary, microtime(true) - $startedAt);
}

function printSummary(string $label, array $summary, float $seconds): void
{
    echo "{$label} (" . number_format($seconds, 2) . "s): ";
    echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    flush();
}
