<?php

namespace App\Models\Forum;

use Illuminate\Database\Eloquent\Model;

class Thread extends Model
{
    protected $connection = 'finobe';
    protected $table = 'forum_threads';
    public $timestamps = false;

    protected $fillable = [
        'category',
        'author',
        'title',
        'comment',
        'date',
        'lastreplied',
        'pinned',
        'locked',
        'edited',
        'edited_date'
    ];
}