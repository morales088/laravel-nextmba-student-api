<?php

namespace App\Models;

use id;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Partnership extends Model
{
    use HasFactory;

    protected $table = 'partnerships';
    protected $fillable = ['student_id', 'affiliate_status', 'affiliate_code', 'withdraw_method', 'percentage'];

    public function student() {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
