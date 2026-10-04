# 🚀 Offline Attendance - Quick Setup Guide

Panduan cepat setup fitur absensi offline dengan auto-sync.

---

## ⚡ 5-Minute Setup

### Step 1: Run Migration

```bash
php artisan migrate
```

**Output:** `Migration table created` ✅

### Step 2: Test API

```bash
# Record attendance
curl -X POST http://localhost/api/attendance/offline/record \
  -H "Content-Type: application/json" \
  -d '{
    "offline_device_id": "tablet-1",
    "student_id": 1,
    "teacher_id": 1,
    "school_class_id": 1,
    "attendance_type": "class",
    "attendance_date": "2026-05-25",
    "status": "hadir"
  }'

# Response
{
  "status": "success",
  "message": "Absensi berhasil dicatat (offline)",
  "data": { ... }
}
```

### Step 3: Sync Records

```bash
# Get unsynced
curl http://localhost/api/attendance/offline/unsynced

# Sync all
curl -X POST http://localhost/api/attendance/offline/sync

# Check stats
curl http://localhost/api/attendance/offline/statistics
```

**Done!** ✅ Offline attendance ready to use.

---

## 📱 For Mobile/Tablet Developers

### React Native / Flutter Setup

1. **Record offline:**

    ```javascript
    // User recorded attendance, save locally
    const offlineData = {
        offline_device_id: "tablet-1",
        student_id: 1,
        attendance_type: "class",
        attendance_date: "2026-05-25",
        status: "hadir",
    };

    // Store in SQLite / AsyncStorage
    await localStorage.setItem(
        "offline-attendance-1",
        JSON.stringify(offlineData),
    );
    ```

2. **Auto-sync on network:**

    ```javascript
    // Detect network
    NetInfo.addEventListener((state) => {
        if (state.isConnected) {
            // Network available, sync
            syncOfflineData();
        }
    });
    ```

3. **Sync function:**
    ```javascript
    async function syncOfflineData() {
        const data = await localStorage.getItem("offline-attendance-1");

        await fetch("https://api.school.com/api/attendance/offline/sync", {
            method: "POST",
            body: JSON.stringify({
                device_id: "tablet-1",
            }),
        });
    }
    ```

---

## 👨‍💻 For Backend Developers

### Service Usage

```php
use App\Services\OfflineAttendanceService;

class AttendanceReportController {
    public function __construct(
        private OfflineAttendanceService $service
    ) {}

    public function getReport() {
        // Get statistics
        $stats = $this->service->getStatistics();

        // Get unsynced for specific device
        $unsynced = $this->service->getUnsyncedByDevice('tablet-1', 50);

        return view('attendance.report', compact('stats', 'unsynced'));
    }
}
```

### Command Usage

```bash
# Schedule in kernel
# Sync every 15 minutes
$schedule->command('attendance:sync --limit=200')->everyFifteenMinutes();
```

---

## 👤 For Admin Users

### Monitor Sync Status

```bash
# Check sync rate
php artisan attendance:sync
```

**Output:**

```
📊 Sync Statistics:

Metric         | Value
-------------- | -------
Total Records  | 1000
Synced         | 950
Unsynced       | 50
Failed         | 0
Sync Rate      | 95.0%
```

### Troubleshoot

```bash
# Get unsynced records
curl http://localhost/api/attendance/offline/unsynced?limit=100

# Retry failed
curl -X POST http://localhost/api/attendance/offline/sync/retry

# Clear old data (keep 30 days)
curl -X DELETE http://localhost/api/attendance/offline/clear-old
```

---

## 🗂️ File Structure

```
app/
├── Models/
│   └── OfflineAttendance.php
├── Services/
│   └── OfflineAttendanceService.php
├── Http/Controllers/
│   └── OfflineAttendanceController.php
├── Console/Commands/
│   └── SyncOfflineAttendanceCommand.php

routes/
└── api.php (offline attendance routes)

database/migrations/
└── 2026_05_25_000005_create_offline_attendances_table.php
```

---

## 🔌 Integration Checklist

- [ ] Migration runned (`php artisan migrate`)
- [ ] API routes registered (`routes/api.php`)
- [ ] Service available for injection
- [ ] Command accessible (`php artisan attendance:sync`)
- [ ] Mobile app detects network connection
- [ ] Mobile app calls sync endpoint when online
- [ ] Monitoring in place (check sync stats regularly)

---

## 💡 Usage Examples

### Example 1: Simple Tablet App

```javascript
// Device: Tablet di kelas
class AttendanceApp {
    async recordAttendance(studentId, status) {
        // Save locally
        const record = {
            offline_device_id: "tablet-kelas-10a",
            student_id: studentId,
            teacher_id: 1,
            school_class_id: 1,
            attendance_type: "class",
            attendance_date: new Date().toISOString().split("T")[0],
            status: status, // 'hadir', 'sakit', 'izin', 'alfa'
        };

        // API call (works offline or online)
        await fetch("/api/attendance/offline/record", {
            method: "POST",
            body: JSON.stringify(record),
        });

        // Show success
        alert(`✅ Absensi ${studentId} tercatat`);
    }

    async autoSync() {
        // Check network periodically
        setInterval(async () => {
            try {
                const response = await fetch("/api/attendance/offline/sync", {
                    method: "POST",
                    body: JSON.stringify({
                        device_id: "tablet-kelas-10a",
                    }),
                });

                if (response.ok) {
                    const data = await response.json();
                    console.log(`✅ Synced ${data.result.synced} records`);
                }
            } catch (e) {
                console.log("⏳ Offline, akan sync nanti");
            }
        }, 30000); // Check setiap 30 detik
    }
}
```

### Example 2: Reports Query

```php
// Get attendance untuk report
$offlineRecords = OfflineAttendance::where('synced', true)
    ->where('attendance_date', '>=', '2026-05-01')
    ->where('attendance_date', '<=', '2026-05-31')
    ->with('student', 'teacher')
    ->get();

$report = $offlineRecords->groupBy('student_id')->map(function ($records) {
    return [
        'student_name' => $records->first()->student->name,
        'total' => $records->count(),
        'hadir' => $records->where('status', 'hadir')->count(),
        'sakit' => $records->where('status', 'sakit')->count(),
        'izin' => $records->where('status', 'izin')->count(),
        'alfa' => $records->where('status', 'alfa')->count(),
    ];
});
```

---

## 📊 Data Flow

```
┌─────────────┐
│   Offline   │
│  Device     │ (Tablet / Phone)
│             │
│ App Records │
│ Attendance  │
└──────┬──────┘
       │
       │ (Save locally)
       ▼
┌──────────────────────┐
│  Local Storage       │
│  (SQLite / File)     │
│                      │
│  - Pending Records   │
│  - UUID Tracking     │
└──────┬───────────────┘
       │
       │ (Detect Network)
       ▼
   Network Up?
       │
      ├─ YES → POST /api/attendance/offline/sync
       │        ▼
       │    Server Validates
       │        ▼
       │    Save to Database
       │        ▼
       │    Return Success
       │        ▼
       │    Update Local (synced=true)
       │
       └─ NO → Keep Trying Every 30s
```

---

## 🎯 Best Practices

1. **Always check network before syncing**

    ```javascript
    if (navigator.onLine) {
        syncOfflineData();
    }
    ```

2. **Generate unique device IDs**

    ```javascript
    const deviceId = `tablet-${roomName}-${serialNumber}`;
    ```

3. **Include timestamps**

    ```javascript
    recorded_at: new Date().toISOString();
    ```

4. **Batch sync for efficiency**

    ```php
    php artisan attendance:sync --limit=500
    ```

5. **Regular cleanup**
    ```bash
    # Monthly cleanup
    php artisan attendance:sync --clear-old --days=30
    ```

---

## 🔗 Complete Docs

See [OFFLINE_ATTENDANCE_GUIDE.md](OFFLINE_ATTENDANCE_GUIDE.md) for full documentation.

---

## 📞 Support

- **Issue with sync?** → Check `/api/attendance/offline/statistics`
- **Records not saving?** → Check device network connectivity
- **Want to retry failed?** → `curl -X POST /api/attendance/offline/sync/retry`
- **Need detailed logs?** → `php artisan tinker`

---

**Version:** 1.0  
**Date:** May 25, 2026  
**Status:** ✅ Ready
