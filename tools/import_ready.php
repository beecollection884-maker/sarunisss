<?php

use App\Services\CsvImportExportService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var CsvImportExportService $service */
$service = $app->make(CsvImportExportService::class);

$imports = [
    'guru' => ['import-guru-final.csv', 'importTeachers'],
    'siswa' => ['import-siswa-final.csv', 'importStudents'],
    'jadwal' => ['import-jadwal-final.csv', 'importSchedules'],
];

$basePath = $app->basePath('storage/app/import-ready');

foreach ($imports as $type => [$filename, $method]) {
    $path = $basePath . DIRECTORY_SEPARATOR . $filename;

    if (! is_file($path)) {
        fwrite(STDERR, "File {$filename} tidak ditemukan.\n");
        exit(1);
    }

    $file = new UploadedFile($path, $filename, 'text/csv', null, true);
    $startedAt = microtime(true);
    $summary = $service->{$method}($file);
    $seconds = number_format(microtime(true) - $startedAt, 2);

    echo strtoupper($type) . " ({$seconds}s): ";
    echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
