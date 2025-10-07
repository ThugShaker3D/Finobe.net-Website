<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoRating extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public $timestamps = false;

    protected $connection = 'finobe';
    protected $table = 'video_ratings';

    protected $fillable = [
        'sender',
        'toid',
        'rate_type',
    ];
}
