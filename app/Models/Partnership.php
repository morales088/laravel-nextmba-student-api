<?php

namespace App\Models;

use id;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Partnership extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'affiliate_status', 'affiliate_code'];

    public function student() {
        return $this->hasOne(Student::class);
    }
}
