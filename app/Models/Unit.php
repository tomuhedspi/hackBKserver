<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;
    protected $fillable = ['code', 'namevn', 'namejp'];

    public function chars ()
    {
        return $this->hasMany(Char::class);
    }
}
