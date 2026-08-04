<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GovOjt extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'training_unit_url',
        'course_code',
        'course_name',
        'training_count',
        'training_hours',
        'student_cost',
        'government_cost',
        'training_location',
        'registration_start_at',
        'registration_end_at',
        'enrollment_status',
        'scheduled_start_date',
        'scheduled_end_date',
        'payment_method',
        'notified_3days_before',
        'notified_1day_before',
        'notified_1hour_before',
        'ai_summary',
    ];

    protected $casts = [
        'registration_start_at' => 'datetime',
        'registration_end_at' => 'datetime',
        'scheduled_start_date' => 'date',
        'scheduled_end_date' => 'date',
    ];
}
