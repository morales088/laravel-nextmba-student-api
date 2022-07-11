<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
// use Laravel\Sanctum\HasApiTokens;
use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Facades\Storage;
use DB;

class Student extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $guarded = ['id'];
    protected $table = 'students';

    // /**
    //  * The attributes that are mass assignable.
    //  *
    //  * @var array<int, string>
    //  */
    // protected $fillable = [
    //     'name',
    //     'email',
    //     'password',
    // ];

    // /**
    //  * The attributes that should be hidden for serialization.
    //  *
    //  * @var array<int, string>
    //  */
    // protected $hidden = [
    //     'password',
    //     'remember_token',
    // ];

    // /**
    //  * The attributes that should be cast.
    //  *
    //  * @var array<string, string>
    //  */
    // protected $casts = [
    //     'email_verified_at' => 'datetime',
    // ];
    
    public static function generate_password($length = 8){
      $chars =  'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    
      $str = '';
      $max = strlen($chars) - 1;
    
      for ($i=0; $i < $length; $i++)
        $str .= $chars[random_int(0, $max)];
    
      return $str;
    }

    public static function uploadProfile($imageRequest, $userId){
      
      // dd($imageRequest, $imageRequest['image']->extension(), $imageRequest['image']);
      
      $imageName = time().'.'.$imageRequest['image']->extension();  
      // dd($request->all(), $imageName);
  
      $path = Storage::disk('s3')->put('images/student_profile', $imageRequest['image']);
      $path = Storage::disk('s3')->url($path);

      DB::table('students')
            ->where('id', $userId)
            ->update(
              [
                'profile_picture' => $path,
                'updated_at' => now(),
              ]
            );

    }
}
