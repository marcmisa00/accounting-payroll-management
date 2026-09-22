<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $connection = 'hris';

    protected $table = 'payroll';

    public $timestamps = false;

    protected $fillable = [
        'periodfrom',
        'periodto',
        'period',
        'days',
        'addedby',
        'addeddatetime',
        'updatedby',
        'updateddatetime',
    ];

    protected $casts = [
        'periodfrom'      => 'date',
        'periodto'        => 'date',
        'addeddatetime'   => 'datetime',
        'updateddatetime' => 'datetime',
    ];
}