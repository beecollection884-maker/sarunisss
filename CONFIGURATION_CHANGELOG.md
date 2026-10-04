# Changelog - Environment Configuration Setup

## 📅 Date: May 25, 2026

## ✅ Changes Made

### 1. Created Configuration File

**File:** `config/schedule.php`

- Centralized schedule configuration
- All settings read from environment variables
- Includes day names localization
- Includes validation options

### 2. Updated Service Classes

#### ScheduleGeneratorService

- Removed hardcoded constants
- Now reads from `config('schedule.*')`
- Updated methods:
    - `initializeTimeSlots()` - uses config for hours, durations, days
    - `findAvailableSlot()` - uses operational days from config
    - `getDayName()` - uses day names from config

#### ScheduleOptimizerService

- Replaced hardcoded day names with `config('schedule.day_names')`
- Updated workload calculation to use `config('schedule.max_teacher_hours_per_week')`
- Methods updated:
    - `analyzeTeacherWorkload()` - uses max hours from config
    - `analyzeDailyDistribution()` - uses operational days from config
    - `getWorkloadStatus()` - dynamically calculates thresholds
    - `getDayName()` - uses config

#### ScheduleDisplayService

- Updated schedule table generation to use config
- Methods updated:
    - `getClassScheduleTable()` - uses operational days & day names from config
    - `getTeacherScheduleTable()` - uses operational days & day names from config

### 3. Updated Environment Files

#### .env

Added schedule configuration variables:

```env
SCHEDULE_SCHOOL_START_HOUR=7
SCHEDULE_SCHOOL_END_HOUR=15
SCHEDULE_LESSON_DURATION=45
SCHEDULE_BREAK_DURATION=30
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4
SCHEDULE_MAX_TEACHER_HOURS=25
SCHEDULE_ROOM_NAME_FORMAT=Ruang {class}
SCHEDULE_SIMULATION_MODE=false
SCHEDULE_ALLOW_SUBJECTS_WITHOUT_TEACHER=false
SCHEDULE_ALLOW_CLASSES_WITHOUT_SUBJECTS=false
SCHEDULE_ALLOW_TEACHERS_WITHOUT_SCHEDULE=false
```

#### .env.example

Added same schedule configuration variables with documentation

### 4. Created Documentation

#### CONFIG_GUIDE.md

- Comprehensive configuration guide
- Explains each environment variable
- Provides examples and scenarios
- Step-by-step instructions for changing settings
- Validation tips and troubleshooting

### 5. Updated Existing Documentation

#### QUICK_START.md

- Updated configuration section
- Points to .env file instead of code
- References CONFIG_GUIDE.md

#### SCHEDULE_GENERATOR_README.md

- Updated configuration section
- Shows example .env settings
- Links to CONFIG_GUIDE.md

---

## 🎯 Benefits

✅ **No Code Edit Required** - Change settings via .env only
✅ **Environment-Specific** - Different configs for dev/staging/production
✅ **Flexible Scheduling** - Easy to adapt to different school schedules
✅ **Scalable** - Can support multiple school configurations
✅ **Documented** - Clear guide for non-technical users

---

## 📋 Configuration Options

| Variable                                 | Default       | Description                        |
| ---------------------------------------- | ------------- | ---------------------------------- |
| SCHEDULE_SCHOOL_START_HOUR               | 7             | School start time (24-hour format) |
| SCHEDULE_SCHOOL_END_HOUR                 | 15            | School end time                    |
| SCHEDULE_LESSON_DURATION                 | 45            | Lesson duration in minutes         |
| SCHEDULE_BREAK_DURATION                  | 30            | Break duration in minutes          |
| SCHEDULE_OPERATIONAL_DAYS                | 0,1,2,3,4     | Operating days (0=Mon, 6=Sun)      |
| SCHEDULE_MAX_TEACHER_HOURS               | 25            | Max teacher hours per week         |
| SCHEDULE_ROOM_NAME_FORMAT                | Ruang {class} | Room naming format                 |
| SCHEDULE_SIMULATION_MODE                 | false         | Enable simulation mode             |
| SCHEDULE_ALLOW_SUBJECTS_WITHOUT_TEACHER  | false         | Allow subjects without teacher     |
| SCHEDULE_ALLOW_CLASSES_WITHOUT_SUBJECTS  | false         | Allow classes without subjects     |
| SCHEDULE_ALLOW_TEACHERS_WITHOUT_SCHEDULE | false         | Allow teachers without schedule    |

---

## 🚀 Usage Example

### Before (Old Way)

Edit code in `app/Services/ScheduleGeneratorService.php`:

```php
private const SCHOOL_START_HOUR = 7;
private const SCHOOL_END_HOUR = 15;
// ... need to restart to see changes
```

### After (New Way)

Edit `.env`:

```env
SCHEDULE_SCHOOL_START_HOUR=7
SCHEDULE_SCHOOL_END_HOUR=15
```

Changes take effect immediately!

---

## 📁 Files Modified/Created

### New Files

- ✨ `config/schedule.php` - Configuration file
- 📖 `CONFIG_GUIDE.md` - Configuration documentation

### Modified Files

- 🔧 `.env` - Added schedule variables
- 🔧 `.env.example` - Added schedule variables
- 📝 `QUICK_START.md` - Updated configuration section
- 📝 `SCHEDULE_GENERATOR_README.md` - Updated configuration section
- 🔄 `app/Services/ScheduleGeneratorService.php` - Uses config()
- 🔄 `app/Services/ScheduleOptimizerService.php` - Uses config()
- 🔄 `app/Services/ScheduleDisplayService.php` - Uses config()

---

## ✅ Testing Recommendations

1. **Test default configuration:**

    ```bash
    php artisan schedule:generate 2025-2026 --validate-only
    ```

2. **Test with modified hours:**

    ```env
    SCHEDULE_SCHOOL_START_HOUR=6
    SCHEDULE_SCHOOL_END_HOUR=16
    ```

3. **Test with different operational days:**

    ```env
    SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4,5  # Include Saturday
    ```

4. **Test with simulation mode:**
    ```env
    SCHEDULE_SIMULATION_MODE=true
    ```

---

## 📚 Documentation Reference

- **Configuration Guide:** [CONFIG_GUIDE.md](CONFIG_GUIDE.md)
- **Quick Start:** [QUICK_START.md](QUICK_START.md)
- **Full Documentation:** [SCHEDULE_GENERATOR_README.md](SCHEDULE_GENERATOR_README.md)
- **Routes:** [SCHEDULE_ROUTES.php](SCHEDULE_ROUTES.php)

---

## 🔄 Migration Notes

**For existing projects:**

1. Run `php artisan config:cache` to cache configs (production)
2. Test schedule generation after changes
3. Verify all services use config() instead of constants

**For new projects:**

- Copy `.env.example` to `.env`
- Update values as needed
- Run migrations and schedule commands

---

**Version:** 1.0  
**Status:** ✅ Complete
