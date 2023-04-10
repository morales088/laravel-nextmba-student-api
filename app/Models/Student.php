<?php

namespace App\Models;

use DB;
use App\Models\Payment;
use App\Models\Partnership;
use App\Models\PartnershipWithdraw;
use Laravel\Passport\HasApiTokens;
// use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Student extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $guarded = ['id'];
    protected $table = 'students';

    public function partnership() {
        return $this->hasOne(Partnership::class, 'student_id');
    }
    
    public function partnership_withraws(){
        return $this->hasMany(PartnershipWithdraw::class);
    }

    public function commision(){
        return $this->hasMany(Payment::class, 'from_student_id');
    }

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

  public static function studentBasicAccount($student_id)
  {

    $student = Student::find($student_id);

    $student->update([
        'account_type' => 2,
        'module_count' => 24,
        'updated_at' => now(),
    ]);

    return $student;
  }
    
    public static function generate_password($length = 8){
      $chars =  'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    
      $str = '';
      $max = strlen($chars) - 1;
    
      for ($i=0; $i < $length; $i++)
        $str .= $chars[random_int(0, $max)];
    
      return $str;
    }

    public static function uploadProfile($request, $userId){
      
      // dd($request, empty($request['profile_image']));
      if(!empty($request['delete_profile']) && $request['delete_profile'] == true){
        $path = null;
      }elseif(!empty($request['profile_image'])){

        $imageName = time().'.'.$request['profile_image']->extension();  
        // dd($request->all(), $imageName);
    
        $path = Storage::disk('s3')->put('images/student_profile', $request['profile_image']);
        $path = Storage::disk('s3')->url($path);

      }else{
        $path = $request['profile_link'];
      }


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
