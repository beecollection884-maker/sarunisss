<?php

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('recover:imported-accounts {--cleanup} {--dry-run}', function () {
    $dryRun = $this->option('dry-run');
    $cleanup = $this->option('cleanup');
    $domain = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'sarunis.local';

    $this->info('Starting imported account recovery');
    $this->newLine();

    $logFile = storage_path('logs/recover_imported_accounts.log');
    $log = static function (string $message) use ($logFile): void {
        file_put_contents($logFile, '[' . now()->toDateTimeString() . '] ' . $message . PHP_EOL, FILE_APPEND);
    };

    $log('Recover imported account command started. dryRun=' . ($dryRun ? 'true' : 'false') . ', cleanup=' . ($cleanup ? 'true' : 'false'));

    $canonicalImportedEmail = static function (string $prefix, mixed $identifier, string $domain): string {
        $local = Str::of($prefix . '.' . (string) $identifier)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->replaceMatches('/\.+/', '.')
            ->trim('.');

        if ($local->isEmpty() || $local->toString() === $prefix) {
            $local = Str::of($prefix . '.' . uniqid());
        }

        return $local->toString() . '@' . $domain;
    };

    $canonicalImportedParentEmail = static function (Student $student): string {
        $motherName = trim((string) ($student->detailSiswa?->mother_name ?? ''));
        $fatherName = trim((string) ($student->detailSiswa?->father_name ?? ''));

        if ($motherName !== '') {
            $parentName = $motherName;
        } elseif ($fatherName !== '') {
            $parentName = $fatherName;
        } else {
            $parentName = 'Orang Tua ' . $student->name;
        }

        $parentPhone = trim((string) ($student->detailSiswa?->parent_phone ?? ''));
        $nameSlug = Str::of($parentName)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->replaceMatches('/\.+/', '.')
            ->trim('.');
        $phoneSlug = Str::of($parentPhone)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->replaceMatches('/\.+/', '.')
            ->trim('.');

        $local = 'orangtua.' . $nameSlug->toString();
        if (! $phoneSlug->isEmpty()) {
            $local .= '.' . $phoneSlug->toString();
        }

        return $local . '@sch.id';
    };

    $recoveredTeacher = 0;
    Teacher::query()
        ->whereNull('user_id')
        ->chunkById(100, function ($teachers) use (&$recoveredTeacher, $domain, $dryRun, $canonicalImportedEmail) {
            foreach ($teachers as $teacher) {
                $canonicalEmail = $canonicalImportedEmail('guru', $teacher->nip ?? $teacher->nik ?? $teacher->name ?? $teacher->id, $domain);
                $user = User::query()->where('email', $canonicalEmail)->first();

                if ($user !== null) {
                    $this->line("Found teacher user for {$teacher->name}: {$canonicalEmail}");
                    $log("Recovered teacher: teacher_id={$teacher->id}, name={$teacher->name}, email={$canonicalEmail}, user_id={$user->id}");

                    if (! $dryRun) {
                        $teacher->forceFill(['user_id' => $user->id])->save();
                        $roles = $user->roles ?? [];
                        if (! in_array(UserRole::GURU_MAPEL->value, $roles, true)) {
                            $roles[] = UserRole::GURU_MAPEL->value;
                            $user->forceFill(['roles' => array_values($roles)])->save();
                        }
                    }

                    $recoveredTeacher++;
                }
            }
        });

    $recoveredStudent = 0;
    Student::query()
        ->whereNull('user_id')
        ->chunkById(100, function ($students) use (&$recoveredStudent, $domain, $dryRun, $canonicalImportedEmail) {
            foreach ($students as $student) {
                $canonicalEmail = $canonicalImportedEmail('siswa', $student->nisn ?? $student->nik ?? $student->name ?? $student->id, $domain);
                $user = User::query()->where('email', $canonicalEmail)->first();

                if ($user !== null) {
                    $this->line("Found student user for {$student->name}: {$canonicalEmail}");
                    $log("Recovered student: student_id={$student->id}, name={$student->name}, email={$canonicalEmail}, user_id={$user->id}");

                    if (! $dryRun) {
                        $student->forceFill(['user_id' => $user->id])->save();
                        $roles = $user->roles ?? [];
                        if (! in_array(UserRole::SISWA->value, $roles, true)) {
                            $roles[] = UserRole::SISWA->value;
                            $user->forceFill(['roles' => array_values($roles)])->save();
                        }
                    }

                    $recoveredStudent++;
                }
            }
        });

    $recoveredParent = 0;
    Student::query()
        ->whereNull('parent_user_id')
        ->with('detailSiswa')
        ->chunkById(100, function ($students) use (&$recoveredParent, $dryRun) {
            foreach ($students as $student) {
                $canonicalEmail = $canonicalImportedParentEmail($student);
                $user = User::query()->where('email', $canonicalEmail)->first();

                if ($user !== null) {
                    $this->line("Found parent user for {$student->name}: {$canonicalEmail}");
                    $log("Recovered parent: student_id={$student->id}, student_name={$student->name}, parent_email={$canonicalEmail}, user_id={$user->id}");

                    if (! $dryRun) {
                        $student->forceFill(['parent_user_id' => $user->id])->save();
                        $roles = $user->roles ?? [];
                        if (! in_array(UserRole::ORANG_TUA->value, $roles, true)) {
                            $roles[] = UserRole::ORANG_TUA->value;
                            $user->forceFill(['roles' => array_values($roles)])->save();
                        }
                    }

                    $recoveredParent++;
                }
            }
        });

    $this->newLine();
    $this->info("Recovered teacher links: {$recoveredTeacher}");
    $this->info("Recovered student links: {$recoveredStudent}");
    $this->info("Recovered parent links: {$recoveredParent}");

    if ($cleanup) {
        $deleted = 0;
        $domain = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'sarunis.local';
        $parentDomain = 'sch.id';

        User::query()
            ->where(function ($query) use ($domain, $parentDomain): void {
                $query->where('email', 'like', 'guru.%@' . $domain)
                    ->orWhere('email', 'like', 'siswa.%@' . $domain)
                    ->orWhere('email', 'like', 'orangtua.%@' . $parentDomain);
            })
            ->doesntHave('teacherProfile')
            ->doesntHave('studentProfile')
            ->doesntHave('parentStudents')
            ->chunkById(100, function ($users) use (&$deleted, $dryRun, $log): void {
                foreach ($users as $user) {
                    $this->line("Deleting duplicate imported user {$user->email}");
                    $log("Deleting duplicate imported user: user_id={$user->id}, email={$user->email}");
                    if (! $dryRun) {
                        $user->delete();
                    }
                    $deleted++;
                }
            });

        $this->newLine();
        $this->info("Deleted duplicate imported users: {$deleted}");
    }

    if ($dryRun) {
        $this->newLine();
        $this->comment('Dry run mode: no database changes were made.');
    }

    $this->info('Imported account recovery complete.');
})->purpose('Recover imported teacher, student, and parent account links from existing users.');

Artisan::command('qa:sarunis-bug-report {--report= : Custom report path, relative to project root or absolute.}', function () {
    $startedAt = now();
    $reportOption = $this->option('report');
    $reportPath = $reportOption
        ? (Str::startsWith((string) $reportOption, [base_path(), DIRECTORY_SEPARATOR]) ? (string) $reportOption : base_path((string) $reportOption))
        : storage_path('app/qa-reports/sarunis-bug-report-' . $startedAt->format('Ymd-His') . '.md');

    $this->info('Running SARUNIS live QA checks...');
    $this->line('Connection: ' . DB::connection()->getName() . ' / ' . DB::connection()->getDriverName());
    $this->line('Database: ' . (string) DB::connection()->getDatabaseName());
    $this->newLine();

    $count = static fn(string $table, callable $callback): int => Schema::hasTable($table)
        ? (int) $callback()
        : 0;

    $checks = [];
    $addCheck = static function (string $id, string $severity, string $status, int|string $actual, string $expected) use (&$checks): void {
        $checks[] = compact('id', 'severity', 'status', 'actual', 'expected');
    };

    $guruOrphan = $count('users', fn() => User::query()
        ->whereJsonContains('roles', UserRole::GURU_MAPEL->value)
        ->doesntHave('teacherProfile')
        ->where('name', 'not regexp', '^Guru [0-9]+$')
        ->where('email', 'not like', '%sarunis.test')
        ->count());
    $addCheck('BUG-01A guru role tanpa profil', 'critical', $guruOrphan === 0 ? 'PASS' : 'FAIL', $guruOrphan, '0 akun');

    $siswaOrphan = $count('users', fn() => User::query()
        ->whereJsonContains('roles', UserRole::SISWA->value)
        ->doesntHave('studentProfile')
        ->where('name', 'not regexp', '^Siswa [0-9]+$')
        ->where('email', 'not like', '%sarunis.test')
        ->count());
    $addCheck('BUG-01B siswa role tanpa profil', 'critical', $siswaOrphan === 0 ? 'PASS' : 'FAIL', $siswaOrphan, '0 akun');

    $parentOrphan = $count('users', fn() => User::query()
        ->whereJsonContains('roles', UserRole::ORANG_TUA->value)
        ->doesntHave('parentStudents')
        ->count());
    $addCheck('BUG-03 orang tua tanpa anak', 'high', $parentOrphan === 0 ? 'PASS' : 'FAIL', $parentOrphan, '0 akun');

    $studentsWithoutClass = $count('students', fn() => Student::query()
        ->whereNull('school_class_id')
        ->count());
    $addCheck('BUG-02 siswa tanpa kelas', 'high', $studentsWithoutClass === 0 ? 'PASS' : 'FAIL', $studentsWithoutClass, '0 siswa');

    $usersWithoutRoles = $count('users', fn() => User::query()
        ->where(function ($query): void {
            $query->whereNull('roles')->orWhereJsonLength('roles', 0);
        })
        ->count());
    $addCheck('BUG-04 akun role kosong', 'medium', $usersWithoutRoles === 0 ? 'PASS' : 'FAIL', $usersWithoutRoles, '0 akun');

    $duplicateImportedTeachers = $count('users', fn() => User::query()
        ->select('name')
        ->whereJsonContains('roles', UserRole::GURU_MAPEL->value)
        ->where('name', 'not regexp', '^Guru [0-9]+$')
        ->where('email', 'not like', '%sarunis.test')
        ->groupBy('name')
        ->havingRaw('COUNT(*) > 1')
        ->get()
        ->count());
    $addCheck('BUG-01C nama guru dengan akun duplikat', 'high', $duplicateImportedTeachers === 0 ? 'PASS' : 'FAIL', $duplicateImportedTeachers, '0 nama');

    $defaultPasswordInCode = Str::contains(
        File::exists(app_path('Services/CsvImportExportService.php')) ? File::get(app_path('Services/CsvImportExportService.php')) : '',
        "IMPORT_DEFAULT_PASSWORD = 'ipyakin2026'"
    );
    $addCheck('SEC-01 password impor hardcoded', 'critical', $defaultPasswordInCode ? 'FAIL' : 'PASS', $defaultPasswordInCode ? 'ditemukan' : 'tidak ditemukan', 'tidak ada konstanta password default');

    $envDebug = filter_var(env('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
    $addCheck('SEC-02 APP_DEBUG runtime', 'critical', $envDebug ? 'FAIL' : 'PASS', $envDebug ? 'true' : 'false', 'false');

    $rows = array_map(
        fn(array $check): array => [$check['status'], $check['severity'], $check['id'], (string) $check['actual'], $check['expected']],
        $checks
    );
    $this->table(['Status', 'Severity', 'Check', 'Actual', 'Expected'], $rows);

    $failed = collect($checks)->where('status', 'FAIL')->count();
    $passed = collect($checks)->where('status', 'PASS')->count();

    $report = [];
    $report[] = '# SARUNIS Live QA Bug Report';
    $report[] = '';
    $report[] = '- Generated at: ' . $startedAt->toDateTimeString();
    $report[] = '- Connection: ' . DB::connection()->getName() . ' / ' . DB::connection()->getDriverName();
    $report[] = '- Database: ' . (string) DB::connection()->getDatabaseName();
    $report[] = "- Result: {$passed} PASS, {$failed} FAIL";
    $report[] = '';
    $report[] = '| Status | Severity | Check | Actual | Expected |';
    $report[] = '|---|---|---|---:|---|';
    foreach ($checks as $check) {
        $report[] = '| ' . implode(' | ', [
            $check['status'],
            $check['severity'],
            str_replace('|', '\\|', $check['id']),
            (string) $check['actual'],
            str_replace('|', '\\|', $check['expected']),
        ]) . ' |';
    }
    $report[] = '';
    $report[] = '## Notes';
    $report[] = '';
    $report[] = '- FAIL means the live database or current source still matches the reported bug/risk.';
    $report[] = '- PASS means this QA check did not find that condition at runtime.';
    $report[] = '- Run with `php artisan qa:sarunis-bug-report` after starting the app/database.';
    $report[] = '';

    File::ensureDirectoryExists(dirname($reportPath));
    File::put($reportPath, implode(PHP_EOL, $report));

    $this->newLine();
    $failed > 0
        ? $this->error("QA completed with {$failed} failing check(s).")
        : $this->info('QA completed with all checks passing.');
    $this->line('Report saved to: ' . $reportPath);

    return $failed === 0 ? \Symfony\Component\Console\Command\Command::SUCCESS : \Symfony\Component\Console\Command\Command::FAILURE;
})->purpose('Run live QA checks for the SARUNIS bug report and write a Markdown report.');


