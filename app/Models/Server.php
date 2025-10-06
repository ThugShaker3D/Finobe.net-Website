<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public $timestamps = false;

    protected $connection = 'finobe';
    protected $table = 'servers';
    
    protected $fillable = [
        'ip',
        'port',
        'placeid',
        'players',
        'jobId',
        'status',
    ];
}
