<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'order',
        'thumbnail_url',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}