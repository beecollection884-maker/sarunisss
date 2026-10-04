<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\ClearsDashboardCaches;

class StudentDetail extends Model
{
    use HasFactory, ClearsDashboardCaches;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'student_id',
        'religion',
        'birth_place',
        'address_street',
        'address_village',
        'address_district',
        'address_province',
        'address_city',
        'father_name',
        'father_education',
        'father_occupation',
        'mother_name',
        'mother_education',
        'mother_occupation',
        'parent_address',
        'parent_province',
        'parent_city',
        'postal_code',
        'parent_phone',
        'previous_school',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
