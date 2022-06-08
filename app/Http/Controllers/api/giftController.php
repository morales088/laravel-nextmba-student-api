<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Mail;
use App\Mail\AccountCredentialEmail;
use App\Mail\GiftEmail;
use App\Models\Courseinvitation;
use App\Models\Studentcourse;
use App\Models\Student;
use DB;

class giftController extends Controller
{

    public function getGift(Request $request){
        $userId = auth('api')->user()->id;

        $courses = DB::SELECT("select c.id course_id, c.name course_name, pi.quantity course_qty, p.id payment_id, p.student_id
                                from payments p
                                left join payment_items pi ON p.id = pi.payment_id
                                left join courses c ON c.id = pi.product_id
                                where pi.status <> 0 and c.id <> 0 and p.status = 'Paid' and p.student_id = $userId");

        foreach ($courses as $key => $value) {

            $owner = DB::SELECT("SELECT email FROM students where id = $userId and status <> 0");

            $gift = DB::SELECT("SELECT concat(email, ' (', (CASE WHEN status = 1 THEN 'pending' WHEN status = 2 THEN 'active' END) ,')') email FROM course_invitations where from_student_id = $userId and from_payment_id = $value->payment_id and course_id = $value->course_id and status <> 0");

            foreach ($gift as $key2 => $value2) {
                array_push($owner, $value2);
            }
            
            $value->users = $owner;
            
        }

        return response(["courses" => $courses], 200);
    }

    public function sendGift(Request $request){
        $userId = auth('api')->user()->id;
        $fe_link = env('FRONTEND_LINK');
        // $encrypt = encrypt($userId);
        // $decrypt = decrypt($encrypt);

        $request->validate([
            'course_id' => 'required|numeric|min:1|exists:courses,id',
            'payment_id' => 'required|numeric|min:1|exists:payments,id',
            'email' => 'required|string|email',
        ]);

        $info = ['frm_id' => $userId, 'course_id' => $request->course_id, 'payment_id' => $request->payment_id, 'email' => $request->email];
        $encoded = json_encode($info);
        $code = encrypt($encoded);
        // dd($md5, $code, $md52);
        
        // check if there is course slot from user id
        $check_qty = COLLECT(\DB::SELECT("SELECT * from studentcourses where studentId = $userId and courseId = $request->course_id and status <> 0"))->first();
        $check_recipient_course = DB::SELECT("select *
                    from students s
                    left join studentcourses sc ON s.id = sc.studentId
                    where s.status <> 0 and sc.status <> 0 and s.email = '$request->email' and sc.courseId = $request->course_id");
        // dd($check_qty, !empty($check_recipient_course));

        if($check_qty->quantity < 0 || !empty($check_recipient_course)){
            return response()->json(["message" => "zero courses available / recipient already has this course"], 422);
        }

        // dd($check, $sender);
        
        //generate link
        $sender = Student::find($userId);
        $course = COLLECT(\DB::SELECT("SELECT * FROM courses where id = $request->course_id"))->first();
        
        dd($user);

        // insert data to course_invitations
        Courseinvitation::create($request->only('icon') + 
        [
            'from_student_id' => $userId,
            'from_payment_id' => $request->payment_id,
            'course_id' => $request->course_id,
            'email' => $request->email,
            'code' => $code
        ]);


        // minus qty to student courses
        DB::table('studentcourses')
                        ->where('id', $check->id)
                        ->update(['quantity' => --$check->quantity, 'updated_at' => now()]);


        
        // check if email is already registered / already 
        $recipient = COLLECT(\DB::SELECT("SELECT * FROM students where email = '$request->email' and status <> 0"))->first();

        if(empty($recipient)){
            //generate link
            $link = "$fe_link/register/invite/$code/?email=$request->email";

            // send link to email **********************
                    
            $user = [
                'email_sender' => $sender->email,
                'course' => $course->name,
                'link' => $link,
            ];
            Mail::to($request->email)->send(new GiftEmail($user));

            return response(["message" => "course sent to $request->email"], 200);
        }


        // add studentcourses
        $data = ['studentId' => $recipient->id, 'courseId' => $request->course_id, 'qty' => 1];
        Studentcourse::insertStudentCourse($data);

        // notify user via email **********************
        $user = [
            'email_sender' => $sender->email,
            'course' => $course->name
        ];
        
        Mail::to($request->email)->send(new GiftEmail($user));

        return response(["message" => "course sent to $request->email"], 200);

    }

    public function register(Request $request){

        $payment = $request->validate([
            'hash' => 'required|string',
            'email' => 'required|string|email',
            'name' => 'required|string',
            'phone' => 'string',
            'location' => 'string',
            'company' => 'string',
            'position' => 'string',
            'field' => 'string',
        ]);

        $decrypt = decrypt($request->hash);
        $data = json_decode($decrypt);

        $check = DB::SELECT("select * from course_invitations where from_student_id = $data->frm_id and from_payment_id = $data->payment_id and course_id = $data->course_id and code = '$request->hash' and email = '$request->email' and status = 1");

        if(empty($check)){
            return response()->json(["message" => "unable to process request (wrong email or wrong hash)"], 422);
        }
        
        $password = Student::generate_password();
        // dd($password);

        $student = Student::create($request->only('phone', 'location', 'company', 'position', 'field') + 
                        [
                            'name' => $request->name,
                            'email' => $request->email,
                            'password' => Hash::make($password),
                            'updated_at' => now()
                        ]);

        $user = [
            'email' => $request->email,
            'password' => $password
        ];

        // send email *******
        $user = [
            'email' => $request->email,
            'password' => $password
        ];
        Mail::to($request->email)->send(new AccountCredentialEmail($user));

        return response(["message" => "success", "student" => $student], 200);
    }
}
