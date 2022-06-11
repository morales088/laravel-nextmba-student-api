<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Studentsetting extends Model
{
    use HasFactory;
    
    protected $guarded = ['id'];
    protected $table = 'student_settings';
}
