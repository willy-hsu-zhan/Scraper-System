<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rent591 extends Model
{
    use HasFactory;

    protected $table = 'rent_591';

    protected $fillable = [
        'id','title','region','price_info','link'
    ];
}
