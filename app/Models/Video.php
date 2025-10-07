<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public $timestamps = false;

    protected $connection = 'finobe';
    protected $table = 'videos';

    protected $fillable = [
        'title',
        'author',
        'filename',
        'thumbnail',
        'description',
        'date',
    ];
}