<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JudicialAuction extends Model
{
    protected $fillable = [
        'court',
        'case_no',
        'auction_date',
        'area',
        'address',
        'price',
        'pdf_url',
    ];
}
