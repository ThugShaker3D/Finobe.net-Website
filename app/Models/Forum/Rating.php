<?php

namespace App\Models\Forum;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    protected $connection = 'finobe';
    protected $table = 'forum_ratings';
    public $timestamps = false;

    protected $fillable = [
        'sender',
        'toid',
        'type',
        'rate_type'
    ];
}