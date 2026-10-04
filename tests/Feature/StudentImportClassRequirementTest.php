<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\CsvImportExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentImportClassRequirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_import_rejects_rows_without_class(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'students-without-class.csv',
            implode("\n", [
                'nik,nisn,name,father_name,parent_phone',
                '3201123456789001,1234567890,Ahmad Tanpa Kelas,Budi,081234567890',
            ]),
        );

        $result = app(CsvImportExportService::class)->importStudents($file);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['failed']);
        $this->assertDatabaseMissing('students', ['nik' => '3201123456789001']);
        $this->assertStringContainsString('Nama Kelas atau ID Kelas wajib diisi', $result['errors'][0]['messages'][0]);
    }

    public function test_student_import_creates_student_with_class_when_class_name_is_present(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'students-with-class.csv',
            implode("\n", [
                'nik,nisn,name,class_name,father_name,parent_phone',
                '3201123456789002,1234567891,Siti Dengan Kelas,7A,Budi,081234567891',
            ]),
        );

        $result = app(CsvImportExportService::class)->importStudents($file);

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['failed']);

        $student = Student::query()->where('nik', '3201123456789002')->firstOrFail();
        $this->assertNotNull($student->school_class_id);
        $this->assertSame('7A', SchoolClass::query()->findOrFail($student->school_class_id)->name);
    }
}
