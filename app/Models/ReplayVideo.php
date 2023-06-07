<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReplayVideo extends Model
{
    use HasFactory;
    
    protected $guarded = ['id'];
    protected $table = 'replay_videos';
}
