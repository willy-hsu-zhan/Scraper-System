<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobFacebook extends Model
{
    protected $table = 'job_facebook';

    protected $fillable = [
        'id','title','level','language','salary','date',
    ];
}
