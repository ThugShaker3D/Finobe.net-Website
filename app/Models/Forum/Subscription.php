<?php

namespace App\Models\Forum;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $connection = 'finobe';
    protected $table = 'subscriptions';
    public $timestamps = false;

    protected $fillable = [
        'username',
        'forumId',
        'date',
    ];
}