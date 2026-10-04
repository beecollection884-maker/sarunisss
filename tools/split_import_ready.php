<?php

$basePath = __DIR__ . '/../storage/app/import-ready';

$jobs = [
    [
        'source' => 'import-siswa-final.csv',
        'target_dir' => 'siswa-parts',
        'prefix' => 'import-siswa-part',
        'rows_per_file' => 15,
    ],
    [
        'source' => 'import-jadwal-final.csv',
        'target_dir' => 'jadwal-parts',
        'prefix' => 'import-jadwal-part',
        'rows_per_file' => 100,
    ],
];

foreach ($jobs as $job) {
    splitCsv(
        $basePath . DIRECTORY_SEPARATOR . $job['source'],
        $basePath . DIRECTORY_SEPARATOR . $job['target_dir'],
        $job['prefix'],
        $job['rows_per_file'],
    );
}

function splitCsv(string $sourcePath, string $targetDir, string $prefix, int $rowsPerFile): void
{
    if (! is_file($sourcePath)) {
        fwrite(STDERR, "File sumber tidak ditemukan: {$sourcePath}\n");
        exit(1);
    }

    if (! is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $input = fopen($sourcePath, 'r');
    if ($input === false) {
        fwrite(STDERR, "File sumber tidak dapat dibaca: {$sourcePath}\n");
        exit(1);
    }

    $header = fgetcsv($input);
    if ($header === false) {
        fwrite(STDERR, "File sumber tidak memiliki header: {$sourcePath}\n");
        exit(1);
    }

    $partNumber = 1;
    $rowCount = 0;
    $totalRows = 0;
    $output = null;

    while (($row = fgetcsv($input)) !== false) {
        if (array_filter($row, fn ($value) => trim((string) $value) !== '') === []) {
            continue;
        }

        if ($output === null || $rowCount >= $rowsPerFile) {
            if (is_resource($output)) {
                fclose($output);
                $partNumber++;
            }

            $partPath = $targetDir . DIRECTORY_SEPARATOR . $prefix . '-' . str_pad((string) $partNumber, 3, '0', STR_PAD_LEFT) . '.csv';
            $output = fopen($partPath, 'w');

            if ($output === false) {
                fwrite(STDERR, "File part tidak dapat dibuat: {$partPath}\n");
                exit(1);
            }

            fputcsv($output, $header);
            $rowCount = 0;
        }

        fputcsv($output, $row);
        $rowCount++;
        $totalRows++;
    }

    if (is_resource($output)) {
        fclose($output);
    }

    fclose($input);

    echo basename($sourcePath) . ': ' . $totalRows . ' baris menjadi ' . $partNumber . ' file di ' . $targetDir . PHP_EOL;
}
