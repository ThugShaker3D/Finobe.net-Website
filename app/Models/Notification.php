<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $connection = 'finobe';
    protected $table = 'pms';
    public $timestamps = false;

    protected $fillable = [
        'touser',
        'message',
        'owner',
        'date',
        'readed',
        'forum_id',
        'reply_id',
    ];
}