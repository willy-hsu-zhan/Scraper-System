<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobChickpt extends Model
{
    protected $table = 'job_chickpt';

    protected $fillable = [
        'id', 'title', 'link', 'company', 'salary', 'place', 'date',
    ];
}
