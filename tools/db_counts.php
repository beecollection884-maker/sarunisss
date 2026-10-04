<?php

use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo json_encode([
    'teachers' => Teacher::query()->count(),
    'students' => Student::query()->count(),
    'teaching_assignments' => TeachingAssignment::query()->count(),
    'users' => User::query()->count(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
