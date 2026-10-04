# 📱 Offline Attendance System

Fitur absensi dengan mode offline dan auto-sync ketika terhubung ke jaringan.

---

## 🎯 Fitur Utama

### ✅ Offline Recording

- Record absensi tanpa internet
- Data disimpan di local device
- UUID untuk tracking unique

### ✅ Auto Sync

- Sync otomatis saat terhubung jaringan
- Conflict detection (jika sudah ada di server)
- Error handling dengan retry mechanism

### ✅ Monitoring

- Real-time sync statistics
- Per-device tracking
- Sync error reporting

### ✅ Management

- Clear old synced records
- Retry failed syncs
- Batch operations

---

## 📊 Database Schema

### offline_attendances Table

```sql
CREATE TABLE offline_attendances (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,

    -- Device Info
    offline_device_id VARCHAR(255),

    -- Relations
    student_id BIGINT FOREIGN KEY,
    teacher_id BIGINT FOREIGN KEY,
    school_class_id BIGINT FOREIGN KEY,
    teaching_assignment_id BIGINT FOREIGN KEY (nullable),

    -- Attendance Data
    attendance_type ENUM('class', 'subject'),
    attendance_date DATE,
    status ENUM('hadir', 'sakit', 'izin', 'alfa'),
    notes TEXT (nullable),

    -- Offline Timing
    recorded_at DATETIME,

    -- Sync Status
    synced BOOLEAN (default: false),
    synced_at DATETIME (nullable),
    sync_error TEXT (nullable),

    -- Tracking
    uuid UUID UNIQUE,

    -- System
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    -- Indexes
    KEY offline_device_id (offline_device_id),
    KEY student_id (student_id),
    KEY attendance_date (attendance_date),
    KEY synced (synced),
    KEY synced_created (synced, created_at)
);
```

---

## 🚀 Quick Start

### 1. Run Migration

```bash
php artisan migrate
```

### 2. Record Offline Attendance

```bash
# Via API (offline device)
POST /api/attendance/offline/record

Body:
{
    "offline_device_id": "tablet-kelas-10a",
    "student_id": 1,
    "teacher_id": 2,
    "school_class_id": 3,
    "attendance_type": "class",
    "attendance_date": "2026-05-25",
    "status": "hadir",
    "notes": "Dicatat via tablet offline"
}

Response:
{
    "status": "success",
    "message": "Absensi berhasil dicatat (offline)",
    "data": {
        "id": 1,
        "offline_device_id": "tablet-kelas-10a",
        "student_id": 1,
        "uuid": "550e8400-e29b-41d4-a716-446655440000",
        "synced": false,
        "created_at": "2026-05-25T10:30:00Z"
    }
}
```

### 3. Auto Sync When Connected

```bash
# Device mendeteksi koneksi jaringan, lalu:
POST /api/attendance/offline/sync?device_id=tablet-kelas-10a

Response:
{
    "status": "success",
    "result": {
        "device_id": "tablet-kelas-10a",
        "synced": 45,
        "failed": 0,
        "total": 45,
        "errors": []
    }
}
```

### 4. Check Status

```bash
GET /api/attendance/offline/statistics

Response:
{
    "status": "success",
    "data": {
        "total": 1000,
        "synced": 950,
        "unsynced": 50,
        "failed": 0,
        "sync_rate": 95.0
    }
}
```

---

## 📡 API Reference

### Record Offline Attendance

```http
POST /api/attendance/offline/record

Body:
{
    "offline_device_id": "string (required)",
    "student_id": "int (required)",
    "teacher_id": "int (required)",
    "school_class_id": "int (required)",
    "teaching_assignment_id": "int (optional)",
    "attendance_type": "enum: class|subject (required)",
    "attendance_date": "date (required)",
    "status": "enum: hadir|sakit|izin|alfa (required)",
    "notes": "string (optional)"
}

Success (201):
{
    "status": "success",
    "message": "Absensi berhasil dicatat (offline)",
    "data": { OfflineAttendance }
}
```

### Get Unsynced Records

```http
GET /api/attendance/offline/unsynced?limit=100

Response (200):
{
    "status": "success",
    "count": 45,
    "data": [ OfflineAttendance[] ]
}
```

### Get Unsynced by Device

```http
GET /api/attendance/offline/unsynced/device?device_id=tablet-1&limit=100

Response (200):
{
    "status": "success",
    "device_id": "tablet-1",
    "count": 20,
    "data": [ OfflineAttendance[] ]
}
```

### Sync Records

```http
POST /api/attendance/offline/sync

Body (optional):
{
    "device_id": "tablet-1 (optional)",
    "limit": 100 (optional, default: 100)
}

Response (200):
{
    "status": "success",
    "message": "Sync process completed",
    "result": {
        "synced": 45,
        "failed": 0,
        "total": 45,
        "errors": []
    }
}
```

### Sync Single Record

```http
POST /api/attendance/offline/sync/1

Response (200):
{
    "status": "success",
    "message": "Record synced successfully",
    "data": { OfflineAttendance }
}
```

### Get Statistics

```http
GET /api/attendance/offline/statistics

Response (200):
{
    "status": "success",
    "data": {
        "total": 1000,
        "synced": 950,
        "unsynced": 50,
        "failed": 0,
        "sync_rate": 95.0
    }
}
```

### Get Device Statistics

```http
GET /api/attendance/offline/statistics/device?device_id=tablet-1

Response (200):
{
    "status": "success",
    "data": {
        "device_id": "tablet-1",
        "total": 100,
        "synced": 95,
        "unsynced": 5,
        "sync_rate": 95.0
    }
}
```

### Get Student Attendance by Date

```http
GET /api/attendance/offline/student/1/2026-05-25

Response (200):
{
    "status": "success",
    "student_id": 1,
    "date": "2026-05-25",
    "count": 5,
    "data": [ OfflineAttendance[] ]
}
```

### Get Device Attendance by Date Range

```http
GET /api/attendance/offline/device/range
    ?device_id=tablet-1
    &start_date=2026-05-20
    &end_date=2026-05-25

Response (200):
{
    "status": "success",
    "device_id": "tablet-1",
    "date_range": {
        "start": "2026-05-20",
        "end": "2026-05-25"
    },
    "count": 120,
    "data": [ OfflineAttendance[] ]
}
```

### Retry Failed Syncs

```http
POST /api/attendance/offline/sync/retry

Body (optional):
{
    "max_retries": 3 (default)
}

Response (200):
{
    "status": "success",
    "result": {
        "retried": 5,
        "synced": 4,
        "still_failed": 1
    }
}
```

### Clear Old Records

```http
DELETE /api/attendance/offline/clear-old

Body (optional):
{
    "days": 30 (default, delete synced records older than 30 days)
}

Response (200):
{
    "status": "success",
    "message": "Deleted 150 old records",
    "deleted_count": 150
}
```

---

## 💻 Service Usage

### In Code

```php
use App\Services\OfflineAttendanceService;

// Inject service
public function someFunction(OfflineAttendanceService $service)
{
    // Record offline attendance
    $record = $service->recordOfflineAttendance([
        'offline_device_id' => 'tablet-1',
        'student_id' => 1,
        'teacher_id' => 2,
        'school_class_id' => 3,
        'attendance_type' => 'class',
        'attendance_date' => '2026-05-25',
        'status' => 'hadir',
    ]);

    // Get unsynced
    $unsynced = $service->getUnsyncedRecords(100);

    // Sync all
    $result = $service->syncAllRecords(100);
    // Returns: ['synced' => X, 'failed' => Y, 'total' => Z, 'errors' => [...]]

    // Get statistics
    $stats = $service->getStatistics();
    // Returns: ['total', 'synced', 'unsynced', 'failed', 'sync_rate']
}
```

---

## 🖥️ CLI Command

### Sync Offline Data

```bash
# Sync all records
php artisan attendance:sync

# Sync specific device
php artisan attendance:sync --device=tablet-1

# Sync with limit
php artisan attendance:sync --limit=50

# Retry failed syncs
php artisan attendance:sync --retry-errors

# Clear old records
php artisan attendance:sync --clear-old

# Combined
php artisan attendance:sync --device=tablet-1 --limit=50 --retry-errors
```

### Output Example

```
🔄 Starting offline attendance sync...

📱 Syncing records for device: tablet-1
[████████████████████] 100%

Synced | Failed
   45  |   0

✅ Successfully synced 45 records

📊 Sync Statistics:

Metric          | Value
----- --------- | ------
Total Records   | 1000
Synced          | 950
Unsynced        | 50
Failed          | 0
Sync Rate       | 95.0%

✅ Sync completed!
```

---

## 🔄 Auto-Sync Implementation (Frontend)

### JavaScript/React Example

```javascript
// Detect network connection
window.addEventListener("online", async () => {
    console.log("🌐 Network connected! Syncing offline data...");

    await fetch("/api/attendance/offline/sync", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            device_id: "tablet-1",
        }),
    })
        .then((res) => res.json())
        .then((data) => {
            if (data.status === "success") {
                console.log(`✅ Synced ${data.result.synced} records`);
                // Show success message to user
            }
        })
        .catch((err) => {
            console.error("❌ Sync failed:", err);
            // Show error message to user
        });
});

// Network detection fallback
setInterval(async () => {
    try {
        const res = await fetch("/api/attendance/offline/statistics");
        if (res.ok) {
            // Network is up, sync
            // ... perform sync
        }
    } catch {
        // Network is down, continue offline
    }
}, 30000); // Check every 30 seconds
```

### Flutter Example

```dart
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:http/http.dart' as http;

void setupOfflineSync() {
    Connectivity().onConnectivityChanged.listen((result) {
        if (result != ConnectivityResult.none) {
            // Network connected, sync offline data
            syncOfflineAttendance();
        }
    });
}

Future<void> syncOfflineAttendance() async {
    try {
        final response = await http.post(
            Uri.parse('https://api.school.com/api/attendance/offline/sync'),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({'device_id': 'tablet-1'})
        );

        if (response.statusCode == 200) {
            final data = jsonDecode(response.body);
            print('✅ Synced ${data['result']['synced']} records');
        }
    } catch (e) {
        print('❌ Sync error: $e');
    }
}
```

---

## 🛠️ Configuration

### Environment Variables (Optional)

```env
OFFLINE_ATTENDANCE_SYNC_BATCH_SIZE=100
OFFLINE_ATTENDANCE_RETENTION_DAYS=30
OFFLINE_ATTENDANCE_AUTO_RETRY=true
```

### config/offline-attendance.php (Create if needed)

```php
return [
    'sync' => [
        'batch_size' => env('OFFLINE_ATTENDANCE_SYNC_BATCH_SIZE', 100),
        'retention_days' => env('OFFLINE_ATTENDANCE_RETENTION_DAYS', 30),
        'auto_retry' => env('OFFLINE_ATTENDANCE_AUTO_RETRY', true),
    ],

    'device' => [
        'identifier_pattern' => 'tablet-|laptop-|phone-',
    ],
];
```

---

## 🔍 Monitoring & Debugging

### Check Unsynced Data

```bash
# Via API
curl http://localhost/api/attendance/offline/unsynced?limit=50

# Via CLI
php artisan tinker
>>> App\Models\OfflineAttendance::where('synced', false)->count()
=> 23
```

### Check Sync Errors

```php
// In tinker
OfflineAttendance::whereNotNull('sync_error')->get()
```

### Retry Failed Records

```bash
php artisan attendance:sync --retry-errors

# Or via API
curl -X POST http://localhost/api/attendance/offline/sync/retry
```

---

## 🔒 Security Considerations

1. **Device Authentication** - Optional: Add device registration
2. **Data Validation** - All input validated on server
3. **Conflict Resolution** - Automatic detection & handling
4. **Encryption** - Use HTTPS for API calls
5. **Rate Limiting** - Add throttle middleware if needed

### Optional: Add Auth Middleware

```php
// In OfflineAttendanceController
public function __construct() {
    // $this->middleware('auth:sanctum'); // Optional
    // $this->middleware('throttle:60,1'); // Optional rate limit
}
```

---

## 📈 Performance Tips

1. **Batch Syncing** - Sync in batches (default: 100 records)
2. **Index Optimization** - Indexes on `synced` and `created_at`
3. **Cleanup** - Regularly delete old synced records
4. **Connection Check** - Device checks network every 30s
5. **Progressive Sync** - Large datasets synced progressively

```bash
# Optimize database
php artisan attendance:sync --clear-old  # Run monthly
```

---

## 🧪 Testing

### Test Recording Offline

```bash
curl -X POST http://localhost/api/attendance/offline/record \
  -H "Content-Type: application/json" \
  -d '{
    "offline_device_id": "test-device",
    "student_id": 1,
    "teacher_id": 1,
    "school_class_id": 1,
    "attendance_type": "class",
    "attendance_date": "2026-05-25",
    "status": "hadir"
  }'
```

### Test Sync

```bash
curl -X POST http://localhost/api/attendance/offline/sync \
  -H "Content-Type: application/json" \
  -d '{"device_id": "test-device"}'
```

---

## 🚨 Troubleshooting

### Issue: Records not syncing

**Solution:**

```bash
# Check unsynced count
php artisan tinker
>>> OfflineAttendance::where('synced', false)->count()

# Check for errors
>>> OfflineAttendance::whereNotNull('sync_error')->get()

# Retry
php artisan attendance:sync --retry-errors
```

### Issue: Duplicate records

**Prevention:** Service checks for existing records before syncing

```php
// Service automatically detects and skips
if ($this->recordExists($offlineRecord)) {
    $offlineRecord->markAsSynced();
    return true;
}
```

### Issue: Device not receiving data

**Check:**

```bash
# Verify device ID
GET /api/attendance/offline/statistics/device?device_id=xxx

# Get device records
GET /api/attendance/offline/unsynced/device?device_id=xxx
```

---

## 📚 Related Files

- **Model:** `app/Models/OfflineAttendance.php`
- **Service:** `app/Services/OfflineAttendanceService.php`
- **Controller:** `app/Http/Controllers/OfflineAttendanceController.php`
- **Command:** `app/Console/Commands/SyncOfflineAttendanceCommand.php`
- **Routes:** `routes/api.php` (Offline Attendance section)
- **Migration:** `database/migrations/2026_05_25_000005_create_offline_attendances_table.php`

---

## 🎯 Next Steps

### Optional Enhancements

1. **Device Registration** - Register devices with unique credentials
2. **Encryption** - Encrypt sensitive data in transit
3. **Webhooks** - Notify external systems on sync
4. **Analytics** - Track sync performance & success rates
5. **UI Dashboard** - Web interface for monitoring
6. **Mobile App** - Native offline attendance app

---

**Version:** 1.0  
**Last Updated:** May 25, 2026  
**Status:** ✅ Ready for Production
