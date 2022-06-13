<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use App\Mail\ForgotPassword;
use App\Mail\AccountUpdate;
use App\Models\Module;
use App\Models\Student;
use App\Models\Link;
use App\Models\Studentsetting;
use Mail;
use DB;


class studentController extends Controller
{
    public function getModule(Request $request, $moduleId){

        $request->query->add(['id' => $moduleId]);
        $userId = auth('api')->user()->id;

        $request->validate([
            'id' => 'numeric|min:1|exists:modules,id',
        ]);

        $student_module = COLLECT(\DB::SELECT("select m.*, sm.remarks student_remarks,
        (CASE WHEN m.status = 1 THEN 'draft' WHEN m.status = 2 THEN 'published' WHEN m.status = 3 THEN 'archived' END) module_status,
        (CASE WHEN m.broadcast_status = 1 THEN 'offline' WHEN m.broadcast_status = 2 THEN 'live' WHEN m.broadcast_status = 3 THEN 'pending_replay' WHEN m.broadcast_status = 4 THEN 'replay' END) broadcast_status,
		(CASE WHEN sm.status = 1 THEN 'active' WHEN sm.status = 2 THEN 'pending' WHEN sm.status = 3 THEN 'completed' END) student_module_status
        from modules m
        left join student_modules sm ON m.id = sm.moduleId
        where sm.status <> 0 and m.status <> 0 and m.id = $moduleId and sm.studentId = $userId"))->first();

        $student_module->topics = DB::SELECT("select t.id topic_id, t.moduleId, t.name topic_name, t.video_link topic_video_link, t.description topic_description,
                                                s.name speaker_name, s.position speaker_position, s.company speaker_company, s.profile_path speaker_profile_path, s.company_path speaker_company_path
                                                from student_modules sm
                                                left join topics t ON sm.moduleId = t.moduleId
                                                left join speakers s ON s.id = t.speakerId
                                                where sm.status <> 0 and t.status <> 0 and s.status <> 0
                                                and sm.moduleId = $moduleId and sm.studentId = $userId");

        $student_module->extra_videos = DB::SELECT("SELECT * FROM extra_videos where moduleId = $moduleId and status <> 0");

        $student_module->files = DB::SELECT("SELECT * FROM module_files where moduleId = $moduleId and status <> 0");

        // dd($request->all(), $student_module);
        return response(["student_module" => $student_module], 200);
    }

    public function getCoursesByType(Request $request, $course_type = 'all'){
        
        $userId = auth('api')->user()->id;
        $request->query->add(['course_type' => $course_type]);

        $request->validate([
            'course_type' => [
                        'string',
                        Rule::in(['all', 'active', 'completed']),
                    ],
        ]);
        
        if($request->course_type == 'active'){
            // active
            $courses = DB::SELECT("select *                                
                                    from
                                    (select c.*, sc.starting, sc.expirationDate,
                                    SUM(CASE WHEN sm.status = 1 THEN 1 ELSE 0 END) AS `incomple_modules`,
                                    SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) AS `complete_modules`,
                                    count(sm.id) total_st_modules,
                                    -- ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) score_percentage
                                    IF(SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) > 11, 100.00, ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / 12) * 100 ), 0 )) score_percentage
                                    from courses c
                                    left join modules m ON m.courseId = c.id
                                    left join student_modules sm ON m.id = sm.moduleId
                                    left join studentcourses sc ON c.id = sc.courseId and sc.studentId = sm.studentId
                                    where c.status <> 0 and m.status = 2 and sm.status <> 0 and sc.status <> 0 and sm.studentId = $userId
                                    group by c.id) c where c.score_percentage < 100");
                                    

        }elseif($request->course_type == 'completed'){
            // complete
            $courses = DB::SELECT("select *
                                    from
                                    (select c.*, sc.starting, sc.expirationDate,
                                    SUM(CASE WHEN sm.status = 1 THEN 1 ELSE 0 END) AS `incomple_modules`,
                                    SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) AS `complete_modules`,
                                    count(sm.id) total_st_modules,
                                    -- ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) score_percentage
                                    IF(SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) > 11, 100.00, ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / 12) * 100 ), 0 )) score_percentage
                                    from courses c
                                    left join modules m ON m.courseId = c.id
                                    left join student_modules sm ON m.id = sm.moduleId
                                    left join studentcourses sc ON c.id = sc.courseId and sc.studentId = sm.studentId
                                    where c.status <> 0 and m.status = 2 and sm.status <> 0 and sc.status <> 0 and sm.studentId = $userId
                                    group by c.id) c where c.score_percentage = 100");
        }else{
            // all
            // $courses = DB::SELECT("select c.*,
            //     SUM(CASE WHEN sm.status = 1 THEN 1 ELSE 0 END) AS `incomple_modules`,
            //     SUM(CASE WHEN sm.status = 2 THEN 1 ELSE 0 END) AS `complete_modules`,
            //     count(sm.id) total_st_modules,
            //     ROUND( ( (SUM(CASE WHEN sm.status = 2 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) percentage,
            //     sm.studentId
            //     from courses c
            //     left join modules m ON m.courseId = c.id
            //     left join student_modules sm ON m.id = sm.moduleId
            //     where c.status <> 0 or m.status <> 0 or sm.status <> 0
            //     group by c.id");

            $courses = DB::SELECT("SELECT * FROM courses c where c.status <> 0");
            
            foreach ($courses as $key => $value) {
                $check = COLLECT(\DB::SELECT("select c.*, sc.starting, sc.expirationDate,
                                                SUM(CASE WHEN sm.status = 1 THEN 1 ELSE 0 END) AS `incomple_modules`,
                                                SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) AS `complete_modules`,
                                                count(sm.id) total_st_modules,
                                                -- ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) score_percentage
                                                IF(SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) > 11, 100.00, ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / 12) * 100 ), 0 )) score_percentage
                                                from courses c
                                                left join studentcourses sc ON c.id = sc.courseId
                                                left join modules m ON m.courseId = c.id
                                                left join student_modules sm ON m.id = sm.moduleId and sc.studentId = sm.studentId
                                                where c.status <> 0 and m.status = 2 and sm.status <> 0 and sc.status <> 0 and sm.studentId = $userId and c.id = $value->id"))->first();
                                                // dd($check);
                if($check){
                    $value->starting = $check->starting;
                    $value->expirationDate = $check->expirationDate;
                    $value->incomple_modules = $check->incomple_modules;
                    $value->complete_modules = $check->complete_modules;
                    $value->total_st_modules = $check->total_st_modules;
                    $value->score_percentage = $check->score_percentage;
                }
            }

        }

        // dd($courses);
        return response(["courses" => $courses, "type" => $request->course_type], 200);
    }

    public function getStudentInfo(Request $request){
        $userId = auth('api')->user()->id;

        $request->query->add(['id' => $userId]);

        $request->validate([
            'id' => 'numeric|min:1|exists:students,id',
        ]);
        
        $student = COLLECT(\DB::SELECT("select id, name, email, phone, location, company, position, field, last_login
                                from students s where id = $request->id"))->first();
                                
        $student->links = DB::SELECT("select * from links where studentId = $student->id");



        return response(["student" => $student], 200);
    }

    public function getLiveModules(Request $request){
        
        $userId = auth('api')->user()->id;

        $live_modules = DB::SELECT("select m.*, c.name course_name
        from student_modules sm
        left join modules m ON m.id = sm.moduleId
        left join courses c on m.courseId = c.id
        where m.broadcast_status = 2 and m.status = 2 and m.status <> 0 and sm.status <> 0 and c.status <> 0 and
        sm.studentId = $userId");

        foreach ($live_modules as $key => $value) {
        $value->topics = DB::SELECT("SELECT t.id topic_id, t.moduleId, t.name topic_name, t.video_link topic_video_link, t.description topic_description,
                            s.name speaker_name, s.position speaker_position, s.company speaker_company, s.profile_path speaker_profile_path, s.company_path speaker_company_path,
                            (CASE WHEN sr.role = 1 THEN 'main' WHEN sr.role = 2 THEN 'guest' END) speaker_role
                            from topics t
                            left join speaker_roles sr ON t.id = sr.topicId
                            left join speakers s on t.speakerId = s.id
                            where t.status <> 0 and sr.status <> 0 and s.status <> 0
                            and t.moduleId = $value->id");
        }
        return response(["modules" => $live_modules], 200);
    }

    public function getUpcomingModules(Request $request){
        
        $userId = auth('api')->user()->id;

        $upcoming_modules = COLLECT(\DB::SELECT("select m.*, c.name course_name
                                        from student_modules sm
                                        left join modules m ON m.id = sm.moduleId
                                        left join courses c on m.courseId = c.id
                                        where m.status <> 0 and sm.status <> 0 and c.status <> 0
                                        and sm.studentId = $userId and m.broadcast_status = 1 and m.status = 2 and m.start_date > '".now()."'"))->first();
                                        
        // foreach ($upcoming_modules as $key => $value) {
            $upcoming_modules->topics = DB::SELECT("SELECT t.id topic_id, t.moduleId, t.name topic_name, t.video_link topic_video_link, t.description topic_description,
                                                    s.name speaker_name, s.position speaker_position, s.company speaker_company, s.profile_path speaker_profile_path, s.company_path speaker_company_path,
                                                    sr.role,
                                                    (CASE WHEN sr.role = 1 THEN 'main' WHEN sr.role = 2 THEN 'guest' END) speaker_role
                                                    from topics t
                                                    left join speaker_roles sr ON t.id = sr.topicId
                                                    left join speakers s on t.speakerId = s.id
                                                    where t.status <> 0 and sr.status <> 0 and s.status <> 0
                                                    and t.moduleId = $upcoming_modules->id");
        // }

        return response(["modules" => $upcoming_modules], 200);
    }

    public static function getCourses(Request $request, $id = 0){
        $userId = auth('api')->user()->id;

        if($id){
            // $courses = COLLECT(\DB::SELECT("select c.*,
            //                     ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) completion_percentage
            //                     from student_modules sm
            //                     left join modules m ON m.id = sm.moduleId
            //                     left join courses c on m.courseId = c.id
            //                     where m.status= 2 and sm.status <> 0 and c.status <> 0
            //                     and sm.studentId = $userId and c.id = $id group by c.id"))->first();

            $courses = COLLECT(\DB::SELECT("select c.*, sc.starting, sc.expirationDate,
                                            SUM(CASE WHEN sm.status = 1 THEN 1 ELSE 0 END) AS `incomple_modules`,
                                            SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) AS `complete_modules`,
                                            count(sm.id) total_st_modules,
                                            -- ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) score_percentage
                                            IF(SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) > 11, 100.00, ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / 12) * 100 ), 0 )) score_percentage
                                            from courses c
                                            left join modules m ON m.courseId = c.id
                                            left join student_modules sm ON m.id = sm.moduleId
                                            left join studentcourses sc ON c.id = sc.courseId and sc.studentId = sm.studentId
                                            where c.status <> 0 and m.status = 2 and sm.status <> 0 and sc.status <> 0 and sm.studentId = $userId and c.id = $id"))->first();
        }else{
            // $courses = DB::SELECT("select c.*, sc.starting start_date, sc.expirationDate expiration_date,
            //                     ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) completion_percentage
            //                     from student_modules sm
            //                     left join modules m ON m.id = sm.moduleId
            //                     left join courses c on m.courseId = c.id
            //                     left join studentcourses sc ON sc.courseId = c.id and sm.studentId = sc.studentId
            //                     where m.status = 2 and sm.status <> 0 and c.status <> 0
            //                     and sm.studentId = $userId group by c.id");

            $courses = DB::SELECT("select c.*, sc.starting, sc.expirationDate,
                                            SUM(CASE WHEN sm.status = 1 THEN 1 ELSE 0 END) AS `incomple_modules`,
                                            SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) AS `complete_modules`,
                                            count(sm.id) total_st_modules,
                                            -- ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) score_percentage
                                            IF(SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) > 11, 100.00, ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / 12) * 100 ), 0 )) score_percentage
                                            from courses c
                                            left join modules m ON m.courseId = c.id
                                            left join student_modules sm ON m.id = sm.moduleId
                                            left join studentcourses sc ON c.id = sc.courseId and sc.studentId = sm.studentId
                                            where c.status <> 0 and m.status = 2 and sm.status <> 0 and sc.status <> 0 and sm.studentId = $userId");
        }

        return response(["course" => $courses], 200);
    }
    
    public function getuPastModules(Request $request, $course_id = 0){
        
        $userId = auth('api')->user()->id;

        // $request->query->add(['course_id' => $course_id]);

        // $request->validate([
        //     'course_id' => 'numeric|min:1|exists:courses,id',
        // ]);

        $courseQuery = null;
        if($course_id){
            $courseQuery = "and c.id = $course_id";
        }

        $courses = DB::SELECT("select c.*
                                from student_modules sm
                                left join modules m ON m.id = sm.moduleId
                                left join courses c on m.courseId = c.id
                                where m. status <> 0 and sm.status <> 0 and c.status <> 0
                                and sm.studentId = 1 $courseQuery group by c.id");

        foreach ($courses as $key => $value) {
            $value->past_module = DB::SELECT("select m.*,
                                                (CASE WHEN sm.status = 1 THEN 'active' WHEN m.status = 2 THEN 'pending' WHEN m.status = 3 THEN 'complete' END) student_module_status
                                                from student_modules sm
                                                left join modules m ON sm.moduleId = m.id
                                                where m.end_date < '".now()."' and sm.studentId = $userId and m.courseId = $value->id and m.status = 2");
        }
        // dd($courses);
        return response(["courses" => $courses], 200);
    }

    public function getModulesByType(Request $request, $course_id, $modules_type = 'live'){
        
        $userId = auth('api')->user()->id;
        $request->query->add(['course_id' => $course_id]);
        $request->query->add(['modules_type' => $modules_type]);

        $request->validate([
            'course_id' => 'numeric|min:1|exists:modules,id',
            'modules_type' => [
                        'string',
                        Rule::in(['live', 'upcoming', 'past']),
                    ],
        ]);

        $modules = Module::getModules($userId, $course_id, $modules_type);
        
        return response(["modules" => $modules], 200);
    }

    public function updateStudent(Request $request){
        $userId = auth('api')->user()->id;

        $request->validate([
            // 'course_id' => 'numeric|min:1|exists:modules,id',
            // 'modules_type' => [
            //             'string',
            //             Rule::in(['live', 'upcoming', 'past']),
            //         ],
        ]);
        // dd($request->all(), $request->has('LI'));

        $student = Student::find($userId);
        
        $student->update($request->only('name', 'email', 'phone', 'location', 'company', 'position', 'field') +
                        [ 'updated_at' => now()]
                        );

        // update student links
        $links = [];

        ($request->has('LI'))? $links += ['li' => addslashes($request->LI)] : '';
        ($request->has('IG'))? $links += ['ig' => addslashes($request->IG)] : '';
        ($request->has('FB'))? $links += ['fb' => addslashes($request->FB)] : '';
        ($request->has('TG'))? $links += ['tg' => addslashes($request->TG)] : '';
        ($request->has('WS'))? $links += ['ws' => addslashes($request->WS)] : '';
                        // dd($links);
        foreach ($links as $key => $value) {
            // $link = collect(\DB::SELECT("SELECT * FROM links where studentId = $id and name = '$key'"))->first();

            $link = Link::where('studentId', $userId)->where('name', $key)->where('status', '<>', 0)->first();
            
            if($link){
                $link->update(
                [ 
                    'link' => $value,
                    'updated_at' => now()
                ]
                );
            }else{
                Link::create($request->only('icon') + 
                [
                    'studentId' => $userId,
                    'name' => $key,
                    'link' => $value
                ]);
            }

        }

        $studentInfos =  Student::find($userId);

        $studentLinks =  Link::where('studentId', $userId)->get();
        
        $studentInfos->links = $studentLinks;

        // dd($request->all(), $studentInfos);

        return response(["student" => $studentInfos], 200);
    }

    public function updatePasword(Request $request){
        $userId = auth('api')->user()->id;

        $request->validate([
            // 'course_id' => 'numeric|min:1|exists:modules,id',
            'old_password' => 'required|string',
            'new_password' => 'required|string',
        ]);

        $student = Student::find($userId);

        // $old_password = $request->old_password = Hash::make($request->old_password);

        if(Hash::check($request->old_password, $student->password)){
            $student->update(
                            [
                                'password' => Hash::make($request->new_password),
                                'updated_at' => now()
                            ]
                            );
        }else{
            return response(["message" => "password does not match"], 409);
        }
        
        // dd(Hash::make($request->old_password), $student->password);
        
        return response(["student" => $student], 200);
    }
    
    public function updateStudentModule(Request $request){
        $userId = auth('api')->user()->id;

        $request->validate([
            'module_id' => 'required|numeric|min:1|exists:modules,id',
        ]);

        DB::table('student_modules')
                        ->where('studentId', $userId)
                        ->where('moduleId', $request->module_id)
                        ->update(['status' => '3', 'updated_at' => now()]);

        return response(["message" => "successfully updated student module's status"], 200);
    }

    public function getBilling(Request $request){
        $userId = auth('api')->user()->id;

        $billing = DB::SELECT("SELECT * FROM payments where student_id = $userId");

        return response(["billing" => $billing], 200);
    }

    public function getPayment(Request $request, $id){

        $payment = DB::SELECT("select *, concat(p.first_name, ' ', p.last_name) name
                                from payments p where p.id = $id");

        return response(["payment" => $payment], 200);

    }

    
    public function getStudentSettings(Request $request){
        $userId = auth('api')->user()->id;
        
        // $request->query->add(['id' => $id]);

        // $request->validate([
        //     'id' => 'required|numeric|min:1|exists:students,id',
        // ]);

        $settings = COLLECT(\DB::SELECT("SELECT * FROM student_settings WHERE studentId = $userId"))->first();

        
        return response(["settings" => $settings], 200);

    }

    public function updateStudentSettings(Request $request){
        $userId = auth('api')->user()->id;
                
        // $request->query->add(['id' => $id]);

        $request->validate([
            'timezone' => 'required|string',
        ]);

        $check = COLLECT(\DB::SELECT("SELECT * FROM student_settings WHERE studentId = $userId"))->first();
        
        if($check){

            $student_setting = Studentsetting::find($check->id);
            $student_setting->update(
                            [ 
                                'timezone' => $request->timezone,
                                'updated_at' => now(),
                            ]
                            );
            return response(["student_setting" => $student_setting], 200);

        }else{

            $student_setting = Studentsetting::create(
                        [
                            'studentId' => $userId,
                            'timezone' => $request->timezone,
                        ]);

            return response(["student_setting" => $student_setting], 200);
        }
    }

    public function forgotPasword(Request $request){
        
        $request->validate([
            'email' => 'required|exists:students,email',
        ]);

        // $expiration_date = now()->addMinutes(30);
        // dd($expiration_date);
        $student = COLLECT(\DB::SELECT("SELECT * from students where email = '$request->email' and status <> 0"))->first();

        $info = ['id' => $student->id];
        $encoded = json_encode($info);
        $code = encrypt($encoded);

        $link = env('APP_URL').'/api/student/confirm_password/?info='.$code;
        
        // dd($request->all(), $code, $info, $link);
        $data = [
            'hash' => $link,
        ];
        Mail::to($request->email)->send(new ForgotPassword($data));

        
        return response(["message" => "success"], 200);
    }

    public function confirmPassword(Request $request){

        $decrypt = decrypt($request->info);
        $data = json_decode($decrypt);

        $password = Student::generate_password();
        $hashPasword = Hash::make($password);
        
        $student = Student::find($data->id);
        
        // dd($student);

        $student->update(
                        [ 
                            'password' => $hashPasword,
                            // 'updated_at' => now()
                        ]
                    );

        $user = [
            'email' => $student->email,
            'password' => $password
        ];

        Mail::to($student->email)->send(new AccountUpdate($user));

        return redirect()->to(env('FRONTEND_LINK'));

        
    }
}
