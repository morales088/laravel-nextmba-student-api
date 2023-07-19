<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Student;
use App\Models\Payment;
use App\Models\User;

class AffiliateWithdraw extends Model
{
    use HasFactory;
    protected $table = 'affiliate_withdraws';

    public function student() {
        return $this->belongsTo(Student::class);
    }

    public function user() {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
