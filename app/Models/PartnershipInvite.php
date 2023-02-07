<?php

namespace App\Models;

use id;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PartnershipInvite extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'invitation_code', 'from_student_id'];

    public function student() {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function from_student() {
        return $this->belongsTo(Student::class, 'from_student_id');
    }
}
