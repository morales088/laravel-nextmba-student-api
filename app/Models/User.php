<?php

namespace App\Models;

use App\Models\AffiliateWithdraw;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    // use HasApiTokens, HasFactory, Notifiable;

    public function affiliate_withdraws(){
        return $this->hasMany(AffiliateWithdraw::class, 'admin_id');
    }
}
