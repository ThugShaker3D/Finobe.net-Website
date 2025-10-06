<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchases extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public $timestamps = false;

    protected $connection = 'finobe';
    protected $table = 'purchases';
    const CREATED_AT = 'date';
    const UPDATED_AT = 'lastchanged';
    
    protected $fillable = [
        'username',
        'assetid',
        'serial',
        'date',
        'lastchanged',
        'author',
        'amount',
        'type',
    ];
}
