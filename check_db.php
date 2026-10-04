<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'guru.kelas@sarunis.test')->first();
if (!$user) {
    echo "User not found\n";
    exit;
}
$teacher = $user->teacher;
if (!$teacher) {
    echo "Teacher not found\n";
    exit;
}
echo "Teacher ID: " . $teacher->id . "\n";
echo "Is Homeroom: " . (App\Models\SchoolClass::where('homeroom_teacher_id', $teacher->id)->exists() ? 'Yes' : 'No') . "\n";
$class = App\Models\SchoolClass::where('name', 'DEMO KELAS HARI INI')->first();
echo "Class Homeroom ID: " . ($class ? $class->homeroom_teacher_id : 'null') . "\n";
