<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DB;

class Studentcourse extends Model
{
    use HasFactory;
    
    protected $guarded = ['id'];

    public static function insertStudentCourse($data){ 
        $student_id = $data['studentId'];
        $course_id = $data['courseId'];
        // $qty = $data['qty'];
        $starting_date = isset($data['starting_date'])? $data['starting_date'] : now();
        $expiration_date = isset($data['expiration_date'])? $data['expiration_date'] : now()->addMonths(12);
        
        $checker = DB::SELECT("SELECT * FROM studentcourses where studentId = $student_id and courseId = $course_id");
        
        if(!empty($checker)){
            return false;
        }
        
        DB::transaction(function() use ($student_id, $course_id, $starting_date, $expiration_date) {

            $Studentcourse = Studentcourse::create(
                                        [
                                            'studentId' => $student_id,
                                            'courseId' => $course_id,
                                            'starting' => $starting_date,
                                            'expirationDate' => $expiration_date,
                                            // 'quantity' => --$qty,
                                        ]);

            $modules = Module::Where('courseId', $course_id)->get();

            foreach ($modules as $key => $value) {
                
                Studentmodule::create(
                    [
                        'studentId' => $student_id,
                        'moduleId' => $value->id,
                    ]);
            }

            return true;

        });

        return true;


    }
}
