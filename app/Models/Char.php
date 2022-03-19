<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Char extends Model
{
    use HasFactory;
    protected $fillable = ['word', 'read', 'note', 'image', 'book', 'status', 'meaning'];
    
    public function comments ()
    {
        return $this->hasMany(Comment::class);
    }
}
