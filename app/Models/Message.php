<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public $timestamps = false;

    protected $connection = 'finobe';
    protected $table = 'messages';

    protected $fillable = [
        'author',
        'touser',
        'subject',
        'message',
        'date',
        'readed',
        'archived',
    ];
}
