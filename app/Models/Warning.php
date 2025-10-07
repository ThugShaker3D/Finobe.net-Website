<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warning extends Model
{
    protected $connection = 'finobe';
    protected $table = 'warning';
    public $timestamps = false;

    protected $fillable = [
        'username',
        'reason',
        'moderator',
        'reactivated',
        'date',
        'undone',
    ];
}