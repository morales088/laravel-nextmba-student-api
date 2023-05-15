<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Mail;
use App\Mail\AccountCredentialEmail;
use App\Mail\GiftEmail;
use App\Models\Courseinvitation;
use App\Models\Studentcourse;
use App\Models\Student;
use App\Models\Course;
use DB;

class giftController extends Controller
{

    public function getGift(Request $request){
        $userId = auth('api')->user()->id;
        $date = env('GIFTABLE_DATE');
        
        // sleep(1); // slowdown the request for set seconds
        // $courses = DB::SELECT("select c.id course_id, c.name course_name, pi.quantity course_qty, p.id payment_id, p.student_id, sc.quantity as unconsumed_course, IF(p.created_at < '$date', true, false) is_giftable
        //                     from payments p
        //                     left join payment_items pi ON p.id = pi.payment_id
        //                     left join courses c ON c.id = pi.product_id
        //                     left join studentcourses sc ON sc.studentId = p.student_id and sc.courseId = c.id
        //                     where pi.status <> 0 and c.id <> 0 and sc.status <> 0 and p.status = 'Paid' and p.student_id = $userId");

        $courses = DB::SELECT("select c.id course_id, c.name course_name, pi.quantity course_qty, p.id payment_id, p.student_id, pi.giftable as unconsumed_course, IF(p.created_at > '$date', true, false) is_giftable, p.created_at
                            from payments p
                            left join payment_items pi ON p.id = pi.payment_id
                            left join courses c ON c.id = pi.product_id
                            where pi.status <> 0 and c.status <> 0 and p.status = 'Paid' and p.student_id = $userId");
                            
        foreach ($courses as $key => $value) {
            
            $user = [];

            // dd($owner);
            if($key == 0){
                $owner = collect(\DB::SELECT("SELECT email, last_login FROM students where id = $userId and status <> 0"))->first();
                array_push($user, $owner);
            }
            // $gift = DB::SELECT("SELECT email,
            //                         id gift_id,
            //                         (CASE WHEN status = 1 THEN 'pending' WHEN status = 2 THEN 'active' END) status FROM course_invitations 
            //                         where from_student_id = $userId and from_payment_id = $value->payment_id and course_id = $value->course_id and status <> 0");

            $gift = DB::SELECT("SELECT ci.email, ci.id gift_id, (CASE WHEN ci.status = 1 THEN 'pending' WHEN ci.status = 2 THEN 'active' END) status, last_login
                                    FROM course_invitations ci
                                    left join students s ON s.email = ci.email
                                    where ci.from_student_id = $userId and ci.from_payment_id = $value->payment_id and ci.course_id = $value->course_id and ci.status <> 0");

            foreach ($gift as $key2 => $value2) {
                array_push($user, $value2);
            }
            
            $value->users = $user;
            
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
        
        // dd($user);

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
                        ->where('id', $check_qty->id)
                        ->update(['quantity' => --$check_qty->quantity, 'updated_at' => now()]);


        
        // check if email is already registered / already 
        $recipient = COLLECT(\DB::SELECT("SELECT * FROM students where email = '$request->email' and status <> 0"))->first();

        if(empty($recipient)){
            //generate link
            // $link = "$fe_link/register/invite/$code/?email=$request->email";
            $link = "$fe_link/register/invite/$request->email/$code";

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
            // 'phone' => 'string',
            // 'location' => 'string',
            // 'company' => 'string',
            // 'position' => 'string',
            // 'field' => 'string',
        ]);

        $decrypt = decrypt($request->hash);
        $data = json_decode($decrypt);

        $check = DB::SELECT("select * from course_invitations where from_student_id = $data->frm_id and from_payment_id = $data->payment_id and course_id = $data->course_id and code = '$request->hash' and email = '$request->email' and status = 1");

        if(empty($check)){
            return response()->json(["message" => "unable to process request (wrong email or wrong hash)"], 422);
        }

        // dd($check);
        DB::table('course_invitations')
                        ->where('id', $check[0]->id)
                        ->update(['status' => 2, 'updated_at' => now()]);
        
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

    public function sendGift2(Request $request){
        $userId = auth('api')->user()->id;
        $fe_link = env('FRONTEND_LINK');
        $giftable_gift = env('GIFTABLE_DATE');
        
        $request->validate([
            'course_id' => 'required|numeric|min:1|exists:courses,id',
            'payment_id' => 'required|numeric|min:1|exists:payments,id',
            'email' => 'required|string|email',
        ]);
        
        // $check_available_qty = COLLECT(\DB::SELECT("SELECT * from studentcourses where studentId = $userId and courseId = $request->course_id and status <> 0"))->first();
        // $available_course_per_payment = COLLECT(\DB::SELECT("select pi.quantity course_qty, count(ci.id) number_of_gift, (pi.quantity - 1) - count(ci.id) available_course
        //                                                         from payments p
        //                                                         left join payment_items pi ON pi.payment_id = p.id
        //                                                         left join course_invitations ci ON ci.from_payment_id = p.id and ci.course_id = pi.product_id
        //                                                         where p.id = $request->payment_id and pi.product_id = $request->course_id"))->first();
        
        // $check_course_id = COLLECT(\DB::SELECT("select pi.* 
        //                                         from payments p
        //                                         left join payment_items pi ON pi.payment_id = p.id
        //                                         where p.id = $request->payment_id 
        //                                         and pi.product_id = $request->course_id 
        //                                         and p.status = 'paid'
        //                                         and pi.product_id = 3"))->first();
                                                
        $available_course_per_payment = COLLECT(\DB::SELECT("select pi.* 
                                                from payments p
                                                left join payment_items pi ON pi.payment_id = p.id
                                                where p.id = $request->payment_id and pi.product_id = $request->course_id and p.status = 'paid'"))->first();

        $check_recipient_course = DB::SELECT("select *
                    from students s
                    left join studentcourses sc ON s.id = sc.studentId
                    where s.status <> 0 and sc.status <> 0 and s.email = '$request->email' and sc.courseId = $request->course_id");
        
        $is_giftable = COLLECT(\DB::SELECT("SELECT * from payments where id = $request->payment_id and created_at > '$giftable_gift'"))->first();

        // dd($check_available_qty->quantity, $check_available_qty->quantity < 0, !empty($check_recipient_course), empty($is_giftable), $is_giftable);
        // dd($available_course_per_payment, $available_course_per_payment->available_course <= 0);

        if($available_course_per_payment->giftable <= 0 || !empty($check_recipient_course) || empty($is_giftable)){
            return response()->json(["message" => "zero courses available / recipient already has this course / course expired"], 422);
        }

        $DBtransaction = DB::transaction(function() use ($request, $userId, $fe_link, $giftable_gift, $check_recipient_course, $available_course_per_payment) {
            $sender = auth('api')->user();
            $course = Course::find($request->course_id);
            
            $email_check = Student::WHERE('email', '=', $request->email)->first();
            
            if($email_check){
                // check if student exist
                //     return student id

                // dd($email_check->id);
                $student_id = $email_check->id;

                // notify user thru email
                $user = [
                    'email_sender' => $sender->email,
                    'course' => $course->name
                ];
                // dd($user);
                Mail::to($request->email)->send(new GiftEmail($user));

            }else{
                // else create student
                //     create student acc
                //     return student id
                $name = strtok($request->email, '@');
                
                $password = Student::generate_password();
                $student = Student::create($request->only('phone', 'location', 'company', 'position', 'field') + 
                            [
                                'name' => $name,
                                'email' => $request->email,
                                'password' => Hash::make($password),
                                'updated_at' => now()
                            ]);

                $student_id = $student->id;

                // send account info thru email
                $user = [
                    'email_sender' => $sender->email,
                    'course' => $course->name,
                    'email' => $request->email,
                    'password' => $password
                ];
                // dd($user);
                Mail::to($request->email)->send(new AccountCredentialEmail($user));

            }
            
            // dd($student_id, $check_available_qty, --$check_available_qty->quantity);

            // add course to student
            $data = ['studentId' => $student_id, 'courseId' => $request->course_id, 'qty' => 1];
            Studentcourse::insertStudentCourse($data);

            // set student to basic account type
            Student::studentBasicAccount($student_id);

            // deduct course to student_course table
                // DB::table('studentcourses')
                // ->where('id', $check_available_qty->id)
                // ->update(['quantity' => --$check_available_qty->quantity, 'updated_at' => now()]);

                DB::table('payment_items')
                ->where('id', $available_course_per_payment->id)
                ->update(['giftable' => --$available_course_per_payment->giftable, 'updated_at' => now()]);


            
            // insert data to course_invitations
            return Courseinvitation::create($request->only('icon') + 
            [
                'from_student_id' => $userId,
                'from_payment_id' => $request->payment_id,
                'course_id' => $request->course_id,
                'email' => $request->email,
                'status' => 2,
                // 'code' => $code              change code to nullable
            ]);
            
        });
        
        return response(["message" => "Gift successfully sent."], 200);

    }
}
