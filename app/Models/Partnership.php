<?php

namespace App\Models;

use id;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Partnership extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'affiliate_status'];
}
