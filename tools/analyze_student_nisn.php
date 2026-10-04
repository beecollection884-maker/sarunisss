<?php

$path = __DIR__ . '/../storage/app/import-ready/import-siswa-final.csv';
$handle = fopen($path, 'r');

if ($handle === false) {
    fwrite(STDERR, "File siswa tidak dapat dibaca.\n");
    exit(1);
}

$header = fgetcsv($handle);
$keys = array_map(
    fn ($value) => strtolower(str_replace(' ', '_', trim((string) $value))),
    $header ?: [],
);

$rowNumber = 1;
$invalid = [];

while (($row = fgetcsv($handle)) !== false) {
    $rowNumber++;
    $data = array_combine($keys, array_pad($row, count($keys), null));
    $nisn = trim((string) ($data['nisn'] ?? ''));

    if ($nisn !== '' && ! preg_match('/^[0-9]{10,20}$/', $nisn)) {
        $invalid[] = [
            'row' => $rowNumber,
            'nisn' => $nisn,
            'name' => $data['nama'] ?? $data['name'] ?? null,
        ];
    }
}

fclose($handle);

echo json_encode([
    'invalid_count' => count($invalid),
    'examples' => array_slice($invalid, 0, 20),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
