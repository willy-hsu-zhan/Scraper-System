<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Job104 extends Model
{
    protected $table = 'job_104';

    protected $fillable = [
        'id', 'title', 'link', 'company', 'salary', 'place', 'date',
    ];
}
