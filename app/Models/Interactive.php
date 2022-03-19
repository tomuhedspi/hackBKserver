<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Interactive extends Model
{
    use HasFactory;
    protected $guarded = [];

    const LIKE = 1;
    const UNLIKE = 2;
    protected $table = 'interactive';
}
