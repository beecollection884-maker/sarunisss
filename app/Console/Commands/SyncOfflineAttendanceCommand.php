<?php

namespace App\Console\Commands;

use App\Services\OfflineAttendanceService;
use Illuminate\Console\Command;

/**
 * Command untuk sync offline attendance data
 * 
 * Usage:
 * php artisan attendance:sync
 * php artisan attendance:sync --device=device-1
 * php artisan attendance:sync --limit=50
 */
class SyncOfflineAttendanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:sync 
                            {--device= : Device ID untuk sync spesifik}
                            {--limit=100 : Jumlah record per sync}
                            {--retry-errors : Retry failed syncs}
                            {--clear-old : Clear old synced records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync offline attendance data ke database online';

    public function __construct(
        protected OfflineAttendanceService $service
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Starting offline attendance sync...');
        $this->newLine();

        // Clear old records jika diminta
        if ($this->option('clear-old')) {
            $this->clearOldRecords();
        }

        // Retry errors jika diminta
        if ($this->option('retry-errors')) {
            $this->retryFailedRecords();
        }

        // Main sync
        $this->syncRecords();

        // Show statistics
        $this->showStatistics();

        $this->info('✅ Sync completed!');
        return Command::SUCCESS;
    }

    /**
     * Sync offline records
     */
    protected function syncRecords(): void
    {
        $limit = (int) $this->option('limit');
        $deviceId = $this->option('device');

        if ($deviceId) {
            $this->info("📱 Syncing records for device: {$deviceId}");
            $result = $this->service->syncDeviceRecords($deviceId, $limit);
        } else {
            $this->info("📱 Syncing all offline records (limit: {$limit})");
            $result = $this->service->syncAllRecords($limit);
        }

        // Show progress
        $progressBar = $this->output->createProgressBar($result['total']);
        $progressBar->setFormat('%current%/%max% [%bar%] %percent:3s%%');

        $progressBar->start();
        for ($i = 0; $i < $result['total']; $i++) {
            $progressBar->advance();
            usleep(10000); // Small delay for UI effect
        }
        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Records', $result['total']],
                ['Synced', $result['synced']],
                ['Failed', $result['failed']],
            ]
        );

        // Show errors if any
        if (! empty($result['errors'])) {
            $this->error('❌ Sync errors:');
            foreach ($result['errors'] as $error) {
                $this->error("  - Record #{$error['id']}: {$error['error']}");
            }
        }

        if ($result['synced'] > 0) {
            $this->info("<info>✅ Successfully synced {$result['synced']} records</info>");
        }
    }

    /**
     * Retry failed records
     */
    protected function retryFailedRecords(): void
    {
        $this->info('🔁 Retrying failed sync records...');

        $result = $this->service->retrySyncErrors(10);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Retried', $result['retried']],
                ['Synced', $result['synced']],
                ['Still Failed', $result['still_failed']],
            ]
        );

        $this->newLine();
    }

    /**
     * Clear old synced records
     */
    protected function clearOldRecords(): void
    {
        $this->info('🗑️  Clearing old synced records...');

        $deleted = $this->service->clearOldSyncedRecords(30);
        $this->info("✅ Deleted {$deleted} old records");
        $this->newLine();
    }

    /**
     * Show statistics
     */
    protected function showStatistics(): void
    {
        $this->info('📊 Sync Statistics:');
        $this->newLine();

        $stats = $this->service->getStatistics();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Records', $stats['total']],
                ['Synced', $stats['synced']],
                ['Unsynced', $stats['unsynced']],
                ['Failed', $stats['failed']],
                ['Sync Rate', $stats['sync_rate'] . '%'],
            ]
        );

        $this->newLine();
    }
}
