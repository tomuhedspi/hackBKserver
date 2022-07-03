<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Char extends Model
{
    use HasFactory;
    protected $fillable = ['word', 'reading', 'note', 'image', 'book', 'status', 'meaning', 'type', 'kun', 'on'];
    const WORD = 0;
    const KANJI = 1;

    public function comments ()
    {
        return $this->hasMany(Comment::class);
    }
}
