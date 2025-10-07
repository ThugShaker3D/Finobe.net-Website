<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public $timestamps = false;

    protected $connection = 'finobe';
    protected $table = 'announcements';

    protected $fillable = [
        'author',
        'message',
        'date',
        'expire',
        'color',
    ];
}
