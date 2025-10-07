<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Keys extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public $timestamps = false;

    protected $connection = 'finobe';
    protected $table = 'invitekeys';

    protected $fillable = [
        'author',
        'IID',
        'creation',
        'dateUsed',
        'used',
        'usedBy',
    ];
}