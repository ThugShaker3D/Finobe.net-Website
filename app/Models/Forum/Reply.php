<?php

namespace App\Models\Forum;

use Illuminate\Database\Eloquent\Model;

class Reply extends Model
{
    protected $connection = 'finobe';
    protected $table = 'forum_replies';
    public $timestamps = false;

    protected $fillable = [
        'toid',
        'author',
        'comment',
        'date',
        'replyTo',
        'sticked',
        'edited',
        'edited_date'
    ];
}