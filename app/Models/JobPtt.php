<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobPtt extends Model
{
    protected $table = 'job_ptt';

    protected $fillable = [
        'id',
        'title',
        'link',
        'company',
        'salary',
        'place',
        'date'
    ];
}
