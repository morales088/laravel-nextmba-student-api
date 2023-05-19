<?php

namespace App\Http\Controllers\api;

use DB;
use Mail;
use App\Mail\Issues;
use App\Models\Link;
use App\Models\Course;
use App\Models\Module;
use App\Models\Student;
use App\Models\Category;
use App\Mail\ChangeEmail;
use App\Mail\AccountUpdate;
use App\Mail\ForgotPassword;
use Illuminate\Http\Request;
use App\Models\Studentmodule;
use App\Models\Studentsetting;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;


class studentController extends Controller
{
    public function getModule(Request $request, $moduleId){

        $request->query->add(['id' => $moduleId]);
        $userId = auth('api')->user()->id;
        $chat_visibility = env('chat_disabled');
        $live_stream_visibility = env('live_stream_disabled');

        $request->validate([
            'id' => 'numeric|min:1|exists:modules,id',
        ]);

        $student_module = COLLECT(\DB::SELECT("select distinct m.*, -- sm.remarks student_remarks,
            (CASE WHEN m.status = 1 THEN 'draft' WHEN m.status = 2 THEN 'published' WHEN m.status = 3 THEN 'archived' END) module_status,
            (CASE WHEN m.broadcast_status = 0 THEN 'start_server' WHEN m.broadcast_status = 1 THEN 'offline' WHEN m.broadcast_status = 2 THEN 'live' WHEN m.broadcast_status = 3 THEN 'pending_replay' WHEN m.broadcast_status = 4 THEN 'replay' END) broadcast_status,
            -- (CASE WHEN sm.status = 1 THEN 'active' WHEN sm.status = 2 THEN 'pending' WHEN sm.status = 3 THEN 'completed' END) student_module_status,
            m.stream_info, m.stream_json, m.uid, m.srt_url
            from modules m
            left join student_modules sm ON m.id = sm.moduleId
            where sm.status <> 0 and m.status <> 0 and m.id = $moduleId"))->first();

        $module = COLLECT(\DB::SELECT("select sm.remarks student_remarks,
                (CASE WHEN sm.status = 1 THEN 'active' WHEN sm.status = 2 THEN 'pending' WHEN sm.status = 3 THEN 'completed' END) student_module_status
                from student_modules sm
                where sm.status <> 0 and sm.studentId = $userId and sm.moduleId = $moduleId"))->first();
        // dd($module ? urldecode($module->description) : 1);
        
        $student_module->description = urldecode($student_module->description);

        $student_module->student_module_status = $module ? $module->student_module_status : null;
        $student_module->student_remarks = $module ? $module->student_remarks : null;

        $topics = DB::SELECT("select distinct t.id topic_id, t.moduleId, t.name topic_name, t.video_link topic_video_link, t.vimeo_url topic_vimeo_url, t.uid topic_uid, t.description topic_description,
                            s.name speaker_name, s.position speaker_position, s.company speaker_company, s.profile_path speaker_profile_path, s.company_path speaker_company_path, s.description speaker_description
                            from modules m
                            left join topics t ON m.id = t.moduleId
                            left join speakers s ON s.id = t.speakerId
                            where t.status <> 0 and s.status <> 0
                            and m.id = $moduleId");
        
        foreach ($topics as $key => $value) {
            $value->topic_description = urldecode($value->topic_description);
            $value->speaker_description = urldecode($value->speaker_description);
        }

        $student_module->topics = $topics;

        $category = Category::where('status', '<>', 0)->where('id', $student_module->category_id)->get();
        
        $student_module->category = $category;

        $student_module->extra_videos = DB::SELECT("SELECT * FROM extra_videos where moduleId = $moduleId and status <> 0");

        $student_module->files = DB::SELECT("SELECT * FROM module_files where moduleId = $moduleId and status <> 0");

        // dd($request->all(), $student_module);
        return response(["student_module" => $student_module, "chat_disabled" => $chat_visibility, "live_stream_visibility" => $live_stream_visibility], 200);
    }

    public function getCoursesByType(Request $request, $course_type = 'all'){
        $module_per_course = env('MODULE_PER_COURSE');
        
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
                                    (select c.*, sc.starting, sc.expirationDate, c.price course_price,
                                    SUM(CASE WHEN sm.status = 1 THEN 1 ELSE 0 END) AS `incomple_modules`,
                                    SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) AS `complete_modules`,
                                    count(sm.id) total_st_modules,
                                    -- ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) score_percentage
                                    IF( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) + sc.completed_modules)  >= $module_per_course, 100.00, ROUND( ( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) + sc.completed_modules) / $module_per_course) * 100 ), 0 )) score_percentage
                                    from courses c
                                    left join modules m ON m.courseId = c.id
                                    left join student_modules sm ON m.id = sm.moduleId
                                    left join studentcourses sc ON c.id = sc.courseId and sc.studentId = sm.studentId
                                    where c.status <> 0 and m.status = 2 and sm.status <> 0 and sc.status <> 0 and sm.studentId = $userId and sc.starting <= m.start_date
                                    group by c.id) c where c.score_percentage < 100");

            foreach ($courses as $key => $value) {
                $value->description = urldecode($value->description);
            }  

        }elseif($request->course_type == 'completed'){
            // complete
            $courses = DB::SELECT("select *
                                    from
                                    (select c.*, sc.starting, sc.expirationDate, c.price course_price,
                                    SUM(CASE WHEN sm.status = 1 THEN 1 ELSE 0 END) AS `incomple_modules`,
                                    SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) AS `complete_modules`,
                                    count(sm.id) total_st_modules,
                                    -- ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) score_percentage
                                    IF( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) + sc.completed_modules)  >= $module_per_course, 100.00, ROUND( ( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) + sc.completed_modules) / $module_per_course) * 100 ), 0 )) score_percentage
                                    from courses c
                                    left join modules m ON m.courseId = c.id
                                    left join student_modules sm ON m.id = sm.moduleId
                                    left join studentcourses sc ON c.id = sc.courseId and sc.studentId = sm.studentId
                                    where c.status <> 0 and m.status = 2 and sm.status <> 0 and sc.status <> 0 and sm.studentId = $userId and sc.starting <= m.start_date
                                    group by c.id) c where c.score_percentage = 100");
                                    
            foreach ($courses as $key => $value) {
                $value->description = urldecode($value->description);
            }  

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

            $courses = DB::SELECT("SELECT *, c.price course_price FROM courses c where c.status <> 0");
            
            foreach ($courses as $key => $value) {

                $value->description = urldecode($value->description);

                $check = COLLECT(\DB::SELECT("select c.*, sc.starting, sc.expirationDate, c.price course_price,
                                                SUM(CASE WHEN sm.status = 1 THEN 1 ELSE 0 END) AS `incomple_modules`,
                                                SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) AS `complete_modules`,
                                                count(sm.id) total_st_modules,
                                                -- ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) score_percentage
                                                IF( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) + sc.completed_modules)  >= $module_per_course, 100.00, ROUND( ( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) + sc.completed_modules) / $module_per_course) * 100 ), 0 )) score_percentage
                                                from courses c
                                                left join studentcourses sc ON c.id = sc.courseId
                                                left join modules m ON m.courseId = c.id
                                                left join student_modules sm ON m.id = sm.moduleId and sc.studentId = sm.studentId
                                                where c.status <> 0 and m.status = 2 and sm.status <> 0 and sc.status <> 0 and sm.studentId = $userId and c.id = $value->id and sc.starting <= m.start_date"))->first();
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
        
        // $student = COLLECT(\DB::SELECT("select id, name, email, phone, location, company, position, field, last_login, profile_picture, created_at, updated_at
        $student = COLLECT(\DB::SELECT("select *
                                from students s where id = $request->id"))->first();
                                
        $student->links = DB::SELECT("select * from links where studentId = $student->id");



        return response(["student" => $student], 200);
    }

    public function getLiveModules(Request $request){
        
        $userId = auth('api')->user()->id;

        // $live_modules = DB::SELECT("select m.*, c.name course_name, c.price course_price
        //                             from studentcourses sc
        //                             left join courses c on sc.courseId = c.id
        //                             left join modules m ON c.id = m.courseId
        //                             where m.broadcast_status = 2 and m.status = 2 and m.status <> 0 and sc.status <> 0 and c.status <> 0 and
        //                             sc.studentId = $userId");

        // foreach ($live_modules as $key => $value) {

        //     $value->description = urldecode($value->description);

        //     $topics = DB::SELECT("SELECT t.id topic_id, t.moduleId, t.name topic_name, t.video_link topic_video_link, t.vimeo_url topic_vimeo_url, t.description topic_description,
        //                     s.name speaker_name, s.position speaker_position, s.company speaker_company, s.profile_path speaker_profile_path, s.company_path speaker_company_path, s.description speaker_description,
        //                     (CASE WHEN sr.role = 1 THEN 'main' WHEN sr.role = 2 THEN 'guest' END) speaker_role
        //                     from topics t
        //                     left join speaker_roles sr ON t.id = sr.topicId
        //                     left join speakers s on t.speakerId = s.id
        //                     where t.status <> 0 and sr.status <> 0 and s.status <> 0
        //                     and t.moduleId = $value->id");

        //     foreach ($topics as $key1 => $value1) {
        //         $value1->topic_description = urldecode($value1->topic_description);
        //         $value1->speaker_description = urldecode($value1->speaker_description);
        //     }
        //     $value->topics = $topics;
        // }

        $student_courses = DB::TABLE('studentcourses as sc')
                                    ->leftJoin('courses as c', 'c.id', '=', 'sc.courseId')
                                    ->where('sc.studentId', $userId)
                                    // ->where('c.is_displayed', 1)
                                    ->where('sc.status', 1)
                                    ->pluck('sc.courseId')
                                    ->toArray();

        // dd($student_courses, implode(',', $student_courses));
        $courses = implode(',', $student_courses);

        $modules = DB::TABLE('courses as c')
                            ->leftJoin('modules as m', 'c.id', '=', 'm.courseId')
                            // ->where('c.is_displayed', 1)
                            ->where('c.status', '<>', 0)
                            ->where('m.status', 2)
                            ->whereIn('m.broadcast_status', [2])
                            // ->where('m.end_date', '>', now())
                            ->select('m.*', 'c.name as course_name', 
                                        DB::RAW("IF(c.paid = 0, true, IF(c.id IN ($courses), true, false ) ) has_access"))
                            ->orderBy('m.start_date', 'asc')
                            ->get();
                                    
        
        if($modules){

            foreach ($modules as $key => $value) {

                $value->description = urldecode($value->description);

                $topics = DB::SELECT("SELECT t.id topic_id, t.moduleId, t.name topic_name, t.video_link topic_video_link, t.vimeo_url topic_vimeo_url, t.description topic_description,
                sr.role, s.id speaker_id, s.name speaker_name, s.position speaker_positon, s.company speaker_company, s.company_path speaker_company_path, s.profile_path speaker_profile_path, s.description speaker_description,
                (CASE WHEN sr.role = 1 THEN 'main' WHEN sr.role = 2 THEN 'guest' END) speaker_role
                from topics t
                left join speaker_roles sr ON t.id = sr.topicId
                left join speakers s on t.speakerId = s.id
                where t.status <> 0 and sr.status <> 0 and s.status <> 0
                and t.moduleId = $value->id");

                foreach ($topics as $key1 => $value1) {
                    $value1->topic_description = urldecode($value1->topic_description);
                    $value1->speaker_description = urldecode($value1->speaker_description);
                }

                $value->topics = $topics;

                $value->category = Category::where('status', '<>', 0)->where('id', $value->category_id)->first();
            }

        }

        return response(["modules" => $modules], 200);
    }

    public function getUpcomingModules(Request $request){
        
        $userId = auth('api')->user()->id;

        $upcoming_modules = COLLECT(\DB::SELECT("select m.*, c.name course_name, c.price course_price
                                        from studentcourses sc
                                        left join courses c on sc.courseId = c.id
                                        left join modules m ON c.id = m.courseId
                                        where m.status <> 0 and sc.status <> 0 and c.status <> 0
                                        and sc.studentId = $userId and m.broadcast_status = 1 and m.status = 2 and m.start_date > '".now()."'"))->first();
                                        
        $upcoming_modules->description = urldecode($upcoming_modules->description);
                //  dd($upcoming_modules);                       
        // foreach ($upcoming_modules as $key => $value) {
            if(!empty($upcoming_modules)){
                $topics = DB::SELECT("SELECT t.id topic_id, t.moduleId, t.name topic_name, t.video_link topic_video_link, t.vimeo_url topic_vimeo_url, t.description topic_description,
                                                    s.name speaker_name, s.position speaker_position, s.company speaker_company, s.profile_path speaker_profile_path, s.company_path speaker_company_path, s.description speaker_description,
                                                    sr.role,
                                                    (CASE WHEN sr.role = 1 THEN 'main' WHEN sr.role = 2 THEN 'guest' END) speaker_role
                                                    from topics t
                                                    left join speaker_roles sr ON t.id = sr.topicId
                                                    left join speakers s on t.speakerId = s.id
                                                    where t.status <> 0 and sr.status <> 0 and s.status <> 0
                                                    and t.moduleId = $upcoming_modules->id");
                foreach ($topics as $key => $value) {
                    $value->topic_description = urldecode($value->topic_description);
                    $value->speaker_description = urldecode($value->speaker_description);
                }
                $upcoming_modules->topics = $topics;
            }
        // }

        return response(["modules" => $upcoming_modules], 200);
    }

    public static function getCourses(Request $request, $id = 0){
        $module_per_course = env('MODULE_PER_COURSE');
        $userId = auth('api')->user()->id;

        // sleep(1); // slowdown the request for set seconds

        if($id){
            // $courses = COLLECT(\DB::SELECT("select c.*,
            //                     ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) completion_percentage
            //                     from student_modules sm
            //                     left join modules m ON m.id = sm.moduleId
            //                     left join courses c on m.courseId = c.id
            //                     where m.status= 2 and sm.status <> 0 and c.status <> 0
            //                     and sm.studentId = $userId and c.id = $id group by c.id"))->first();

            $courses = COLLECT(\DB::SELECT("select c.*, sc.starting, sc.expirationDate, c.price course_price,
                                            SUM(CASE WHEN sm.status = 1 THEN 1 ELSE 0 END) AS `incomple_modules`,
                                            SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) AS `complete_modules`,
                                            count(sm.id) total_st_modules,
                                            -- ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) score_percentage
                                            IF( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) + sc.completed_modules)  >= $module_per_course, 100.00, ROUND( ( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) + sc.completed_modules) / $module_per_course) * 100 ), 0 )) score_percentage
                                            from courses c
                                            left join modules m ON m.courseId = c.id
                                            left join student_modules sm ON m.id = sm.moduleId
                                            left join studentcourses sc ON c.id = sc.courseId and sc.studentId = sm.studentId
                                            where c.status <> 0 and m.status = 2 and sm.status <> 0 and sc.status <> 0 and sm.studentId = $userId and c.id = $id and sc.starting <= m.start_date"))->first();
        $courses->description = urldecode($courses->description);
            
        }else{
            // $courses = DB::SELECT("select c.*, sc.starting start_date, sc.expirationDate expiration_date,
            //                     ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) completion_percentage
            //                     from student_modules sm
            //                     left join modules m ON m.id = sm.moduleId
            //                     left join courses c on m.courseId = c.id
            //                     left join studentcourses sc ON sc.courseId = c.id and sm.studentId = sc.studentId
            //                     where m.status = 2 and sm.status <> 0 and c.status <> 0
            //                     and sm.studentId = $userId group by c.id");

            $courses = DB::SELECT("select c.*, sc.starting, sc.expirationDate, c.price course_price,
                                            SUM(CASE WHEN sm.status = 1 THEN 1 ELSE 0 END) AS `incomple_modules`,
                                            SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) AS `complete_modules`,
                                            count(sm.id) total_st_modules,
                                            -- ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) score_percentage
                                            IF( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) + sc.completed_modules)  >= $module_per_course, 100.00, ROUND( ( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) + sc.completed_modules) / $module_per_course) * 100 ), 0 )) score_percentage
                                            from courses c
                                            left join modules m ON m.courseId = c.id
                                            left join student_modules sm ON m.id = sm.moduleId
                                            left join studentcourses sc ON c.id = sc.courseId and sc.studentId = sm.studentId
                                            where c.status <> 0 and m.status = 2 and sm.status <> 0 and sc.status <> 0 and sm.studentId = $userId and sc.starting <= m.start_date");
            foreach ($courses as $key => $value) {
                $value->description = urldecode($value->description);
            }

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

        $courses = DB::SELECT("select c.*, c.price course_price
                                from studentcourses sc
                                left join courses c on sc.courseId = c.id
                                left join modules m ON c.id = m.courseId
                                where m. status <> 0 and sc.status <> 0 and c.status <> 0
                                and sc.studentId = $userId $courseQuery group by c.id");

        foreach ($courses as $key => $value) {
            $value->description = urldecode($value->description);

            $past_module = DB::SELECT("select m.*,
                                        (CASE WHEN sm.status = 1 THEN 'active' WHEN m.status = 2 THEN 'pending' WHEN m.status = 3 THEN 'complete' END) student_module_status
                                        from student_modules sm
                                        left join modules m ON sm.moduleId = m.id
                                        where m.end_date < '".now()."' and sm.studentId = $userId and m.courseId = $value->id and m.status = 2 and m.broadcast_status in (3,4) order by m.start_date asc");

            foreach ($past_module as $key1 => $value1) {
                $value1->description = urldecode($value1->description);
            }

            $value->past_module = $past_module;
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
            'profile_image' => 'image|mimes:jpeg,png,jpg|max:2048',
            // 'course_id' => 'numeric|min:1|exists:modules,id',
            // 'modules_type' => [
            //             'string',
            //             Rule::in(['live', 'upcoming', 'past']),
            //         ],
        ]);
        
        if( !empty($request->profile_image) || !empty($request->profile_link) || $request->delete_profile == true){
            $path = Student::uploadProfile($request->all(), $userId);
        }

        $student = Student::find($userId);
        // dd(!empty($request->email) && $request->email != $student->email, $student);
        if(!empty($request->email) && $request->email != $student->email){
            
            $previous_email = $student->email;
            
            $user = [
                'email' => $request->email,
            ];

            Mail::to($student->email)->send(new ChangeEmail($user));

        }
        
        $student->update($request->only('name', 'email', 'phone', 'location', 'company', 'position', 'field', 'language') +
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
                $student->update([ 'updated_at' => now()]);
            }else{
                Link::create($request->only('icon') + 
                [
                    'studentId' => $userId,
                    'name' => $key,
                    'link' => $value
                ]);
                $student->update([ 'updated_at' => now()]);
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
        
        $studentModule = Studentmodule::where("studentId", $userId)
        ->where("moduleId", $request->module_id)
        // ->where("status", '<>', 0)
        ->first();

        if($studentModule){
            DB::table('student_modules')
                            ->where('studentId', $userId)
                            ->where('moduleId', $request->module_id)
                            ->update(['status' => '3', 'updated_at' => now()]);
        }else{
            $newStudentModule = new Studentmodule;
            $newStudentModule->studentId = $userId;
            $newStudentModule->moduleId = $request->module_id;
            $newStudentModule->status = 3;
            $newStudentModule->save();
        }

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

        // $info = ['id' => $student->id];
        // $encoded = json_encode($info);
        // $code = encrypt($encoded);

        
        $random = md5(uniqid(rand(), true));
        $updatStudent = Student::find($student->id);
        $updatStudent->update(
                            [ 
                                'forgot_password_code' => $random,
                                'updated_at' => now()
                            ]
                            );
                            
        $link = env('reset_route').'?info='.$random;
        
        // dd($request->all(), $code, $info, $link);
        $data = [
            'hash' => $link,
        ];
        Mail::to($request->email)->send(new ForgotPassword($data));

        
        return response(["message" => "success"], 200);
    }
    
    public function updatePassword(Request $request){
        
        $login = $request->validate([
            'code' => 'required|string',
            'password' => 'required|string|min:8',
        ]);

        $find_user = Student::where('forgot_password_code','=', $request->code)->first();
        
        if($find_user){

            $password = Hash::make($request->password);
            $user = Student::find($find_user->id);
            $user->update(
                        [ 
                            'password' => $password,
                            'forgot_password_code' => null,
                            'updated_at' => now()
                        ]
                        );
                        
            return response()->json(["user" => $user], 200);

        }else{
            return response()->json(["message" => "invalid code"], 204);
        }

    }

    public function confirmPassword(Request $request){

        // $decrypt = decrypt($request->info);
        // $data = json_decode($decrypt);
        
        $password = Student::generate_password();
        $hashPasword = Hash::make($password);

        $student = COLLECT(\DB::SELECT("SELECT * from students where forgot_password_code = '$request->info' and status <> 0"))->first();
        
        if($student != null){

            $student = Student::find($student->id);
        
            // dd($student);
    
            $student->update(
                            [ 
                                'forgot_password_code' => null,
                                'password' => $hashPasword,
                                // 'updated_at' => now()
                            ]
                        );
    
            $user = [
                'email' => $student->email,
                'password' => $password
            ];
    
            Mail::to($student->email)->send(new AccountUpdate($user));
        }

        return redirect()->to(env('FRONTEND_LINK'));

        
    }

    public function modulePerCourse(Request $request){
        
        $userId = auth('api')->user()->id;

        $courses = DB::SELECT("select c.*, c.price course_price
                                from courses c
                                left join studentcourses sc ON sc.courseId = c.id
                                where sc.studentId = $userId and c.status <> 0 and sc.status <> 0");
                    
        $modules = [];

        foreach ($courses as $key => $value) {
            
            $value->description = urldecode($value->description);

            $latest_module = COLLECT(\DB::SELECT("select *
                                from modules m
                                where m.courseId = $value->id and m.end_date >= '".now()."' and m.status = 2 and m.broadcast_status in (1,2)
                                order by m.start_date asc"))->first();

            if(!empty($latest_module)){
                
                $latest_module->description = urldecode($latest_module->description);

                $topics = DB::SELECT("SELECT t.id topic_id, t.moduleId, t.name topic_name, t.video_link topic_video_link, t.description topic_description,
                                                    s.id speaker_id, s.name speaker_name, s.position speaker_position, s.company speaker_company, s.profile_path speaker_profile_path, s.company_path speaker_company_path, s.description speaker_description,
                                                    sr.role,
                                                    (CASE WHEN sr.role = 1 THEN 'main' WHEN sr.role = 2 THEN 'guest' END) speaker_role
                                                    from topics t
                                                    left join speaker_roles sr ON t.id = sr.topicId
                                                    left join speakers s on t.speakerId = s.id
                                                    where t.status <> 0 and sr.status <> 0 and s.status <> 0
                                                    and t.moduleId = $latest_module->id");
                foreach ($topics as $key1 => $value1) {
                    $value1->topic_description = urldecode($value1->topic_description);
                    $value1->speaker_description = urldecode($value1->speaker_description);
                }

                $latest_module->course_name = $value->name;
                $latest_module->topics = $topics;

                array_push($modules, $latest_module);
            }

        }

        return response(["modules" => $modules], 200);
    }

    public function allCourses(Request $request){
        $module_per_course = env('MODULE_PER_COURSE');

        $userId = auth('api')->user()->id;
        $userDate = auth('api')->user()->created_at;

        // sleep(1); // slowdown the request for set seconds
        
        // $active = DB::SELECT("select *                                
        //                             from
        //                             (select c.*, sc.starting, sc.expirationDate, c.price course_price,
        //                             SUM(CASE WHEN sm.status = 1 THEN 1 ELSE 0 END) AS `incomple_modules`,
        //                             SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) AS `complete_modules`,
        //                             count(sm.id) total_st_modules,
        //                             -- ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) score_percentage
        //                             IF( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) + sc.completed_modules)  >= $module_per_course, 100.00, ROUND( ( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) + sc.completed_modules) / $module_per_course) * 100 ), 0 )) score_percentage
        //                             from courses c
        //                             left join modules m ON m.courseId = c.id
        //                             left join student_modules sm ON m.id = sm.moduleId
        //                             left join studentcourses sc ON c.id = sc.courseId and sc.studentId = sm.studentId
        //                             where c.status <> 0 and m.status = 2 and sm.status <> 0 and sc.status <> 0 and sm.studentId = $userId and sc.starting <= m.start_date
        //                             group by c.id) c where c.score_percentage < 100");

        // foreach ($active as $key => $value) {                
        //     $value->description = urldecode($value->description);
        // }        

        // $complete = DB::SELECT("select *
        //                             from
        //                             (select c.*, sc.starting, sc.expirationDate, c.price course_price,
        //                             SUM(CASE WHEN sm.status = 1 THEN 1 ELSE 0 END) AS `incomple_modules`,
        //                             SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) AS `complete_modules`,
        //                             count(sm.id) total_st_modules,
        //                             -- ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) score_percentage
        //                             IF( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) + sc.completed_modules)  >= $module_per_course, 100.00, ROUND( ( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) + sc.completed_modules) / $module_per_course) * 100 ), 0 )) score_percentage
        //                             from courses c
        //                             left join modules m ON m.courseId = c.id
        //                             left join student_modules sm ON m.id = sm.moduleId
        //                             left join studentcourses sc ON c.id = sc.courseId and sc.studentId = sm.studentId
        //                             where c.status <> 0 and m.status = 2 and sm.status <> 0 and sc.status <> 0 and sm.studentId = $userId and sc.starting <= m.start_date
        //                             group by c.id) c where c.score_percentage = 100");
                                    
        // foreach ($complete as $key => $value) {
        //     $value->description = urldecode($value->description);
        // } 


        $all = DB::SELECT("SELECT *, $module_per_course as module_per_course FROM courses c where c.status <> 0");
            
        // foreach ($all as $key => $value) {
        //     $value->description = urldecode($value->description);
        //     $check = COLLECT(\DB::SELECT("select c.*, sc.starting, sc.expirationDate, c.price course_price,
        //                                     SUM(CASE WHEN sm.status = 1 THEN 1 ELSE 0 END) AS `incomple_modules`,
        //                                     SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) AS `complete_modules`,
        //                                     count(sm.id) total_st_modules,
        //                                     -- ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) score_percentage
        //                                     IF( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) + sc.completed_modules)  >= $module_per_course, 100.00, ROUND( ( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) + sc.completed_modules) / $module_per_course) * 100 ), 0 )) score_percentage
        //                                     from courses c
        //                                     left join studentcourses sc ON c.id = sc.courseId
        //                                     left join modules m ON m.courseId = c.id
        //                                     left join student_modules sm ON m.id = sm.moduleId and sc.studentId = sm.studentId
        //                                     where c.status <> 0 and m.status <> 0 and sc.status <> 0 and sc.studentId = $userId and c.id = $value->id and sc.starting <= m.start_date"))->first();
        //                                     // dd($check);
        //     if($check){
        //         $value->starting = $check->starting;
        //         $value->expirationDate = $check->expirationDate;
        //         $value->incomple_modules = $check->incomple_modules;
        //         $value->complete_modules = $check->complete_modules;
        //         $value->total_st_modules = $check->total_st_modules;
        //         $value->score_percentage = $check->score_percentage;
        //     }
        // }

        foreach ($all as $key => $value) {
            
            $student_course = DB::TABLE("studentcourses as sc")
                                ->where("studentId", $userId)
                                ->where("courseId", $value->id)
                                ->where("status", 1)
                                ->get();

            if($value->paid == 0){

                $past_module = DB::TABLE("modules as m")
                                ->where("m.status", 2)
                                ->whereIn("m.broadcast_status", [3,4])
                                ->whereRaw("date(m.start_date) >= date('$userDate')")
                                ->count();

                $value->past_module = 0;
                $value->has_access = 1;

            }elseif($student_course->isEmpty()){

                $value->has_access = 0;
                $value->past_module = 0;

            }else{

                $past_module = DB::TABLE("studentcourses as sc")
                                ->leftJoin("modules as m", "sc.courseId", "=", "m.courseId")
                                ->where("m.status", 2)
                                ->where("sc.status", 1)
                                ->whereIn("m.broadcast_status", [3,4])
                                ->where("sc.courseId", $value->id)
                                ->where("sc.studentId", $userId)
                                ->whereRaw("date(m.start_date) >= date(sc.starting)")
                                ->count();
                
                $value->past_module_count = $past_module;
                $value->has_access = 1;

            }
            
        }
        
        return response(["all" => $all], 200);
        
    }

    public function emailIssue(Request $request){
        
        $concern_recipient = env('concern_recipient');

        $userId = auth('api')->user()->id;
        $userEmail = auth('api')->user()->email;
        $userName = auth('api')->user()->name;

        $request->validate([
            'messages' => 'required|string',
        ]);
        
        // send concern message to email
        $user = [
            'email' => $userEmail,
            'name' => $userName,
            'messages' => $request->messages,
            // 'date' => \DateTime::createFromFormat('Y-m-d H:i:s', now()),
            
        ];
        // dd($user, $concern_recipient);
        Mail::to($concern_recipient)->send(new Issues($user));
        
        return response(["message" => "Concern successfully sent."], 200);
    }

    public function courseProgress(Request $request, $id = 0){
        $module_per_course = env('MODULE_PER_COURSE');
        $userId = auth('api')->user()->id;

        // $courses = COLLECT(\DB::SELECT("select sc.starting, sc.expirationDate, c.price course_price,
        //                                 SUM(CASE WHEN sm.status = 1 THEN 1 ELSE 0 END) AS `incomple_modules`,
        //                                 SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) AS `complete_modules`,
        //                                 count(sm.id) total_st_modules,
        //                                 -- ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) score_percentage
        //                                 IF( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) + sc.completed_modules)  >= $module_per_course, 100.00, ROUND( ( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) + sc.completed_modules) / $module_per_course) * 100 ), 0 )) score_percentage
        //                                 from courses c
        //                                 left join modules m ON m.courseId = c.id
        //                                 left join student_modules sm ON m.id = sm.moduleId
        //                                 left join studentcourses sc ON c.id = sc.courseId and sc.studentId = sm.studentId
        //                                 where c.status <> 0 and m.status = 2 and sm.status <> 0 and sc.status <> 0 
        //                                 and sm.studentId = $userId and c.id = $id and sc.starting <= m.start_date"))->first();

        $courses = Course::query();

        $courses = $courses->leftJoin('modules as m', 'm.courseId', '=', 'courses.id')
                            ->leftJoin('student_modules as sm', 'm.id', '=', 'sm.moduleId')
                            ->leftJoin('studentcourses as sc', function($join)
                            {
                                $join->on('courses.id', '=', 'sc.courseId');
                                $join->on('sc.studentId', '=', 'sm.studentId');
                            })
                            ->where('courses.status', '<>', 0)
                            ->where('m.status', '=', 2)
                            ->where('sm.status', '<>', 0)
                            ->where('sc.status', '<>', 0)
                            ->where('m.pro_access', 0)
                            ->where('sm.studentId', $userId)
                            ->where('courses.id', $id)
                            ->where(DB::raw("DATE(sc.starting)") , '<=', DB::raw("DATE(m.start_date)"));
                            // ->select('m.*');

        // $modules = $courses->select('m.*')->get();
                            
        $stats = $courses->selectRaw("sc.starting, sc.expirationDate,
                                        SUM(CASE WHEN sm.status = 1 THEN 1 ELSE 0 END) AS `incomple_modules`,
                                        SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) AS `complete_modules`,
                                        count(sm.id) total_st_modules,
                                        IF( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) + sc.completed_modules)  >= $module_per_course, 100.00, ROUND( ( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) + sc.completed_modules) / $module_per_course) * 100 ), 0 )) score_percentage");


        // dd($stats->first()->toArray());

        return response(["course" => $stats->first()], 200);
        
    }

    public function getAllModule(Request $request){
        
        $module_per_course = env('MODULE_PER_COURSE');
        $userId = auth('api')->user()->id;

        $student_courses = DB::TABLE('studentcourses as sc')
                            ->leftJoin('courses as c', 'c.id', '=', 'sc.courseId')
                            ->where('sc.studentId', $userId)
                            // ->where('c.is_displayed', 1)
                            ->where('sc.status', 1)
                            ->pluck('sc.courseId')
                            ->toArray();

        // dd($student_courses, implode(',', $student_courses));
        $courses = implode(',', $student_courses);

        $modules = DB::TABLE('courses as c')
                    ->leftJoin('modules as m', 'c.id', '=', 'm.courseId')
                    // ->where('c.is_displayed', 1)
                    ->where('c.status', '<>', 0)
                    ->where('m.status', 2)
                    ->whereIn('m.broadcast_status', [1])
                    ->where('m.end_date', '>', now())
                    ->select('m.*', 'c.name as course_name', 
                                DB::RAW("IF(c.paid = 0, true, IF(c.id IN ($courses), true, false ) ) has_access"))
                    ->orderBy('m.start_date', 'asc')
                    ->get();

        if($modules){
            foreach ($modules as $key => $value) {
                $value->description = urldecode($value->description);

                $topics = DB::SELECT("SELECT t.id topic_id, t.moduleId, t.name topic_name, t.video_link topic_video_link, t.vimeo_url topic_vimeo_url, t.description topic_description,
                                    sr.role, s.id speaker_id, s.name speaker_name, s.position speaker_positon, s.company speaker_company, s.company_path speaker_company_path, s.profile_path speaker_profile_path, s.description speaker_description,
                                    (CASE WHEN sr.role = 1 THEN 'main' WHEN sr.role = 2 THEN 'guest' END) speaker_role
                                    from topics t
                                    left join speaker_roles sr ON t.id = sr.topicId
                                    left join speakers s on t.speakerId = s.id
                                    where t.status <> 0 and sr.status <> 0 and s.status <> 0
                                    and t.moduleId = $value->id");
                
                foreach ($topics as $key1 => $value1) {
                    $value1->topic_description = urldecode($value1->topic_description);
                    $value1->speaker_description = urldecode($value1->speaker_description);
                }

                $value->topics = $topics;

                $value->extra_videos = DB::SELECT("SELECT * FROM extra_videos where moduleId = $value->id and status <> 0");

                $value->category = Category::where('status', '<>', 0)->where('id', $value->category_id)->first();
            }
        }
        
        // dd($modules->toArray());

        return response(["modules" => $modules], 200);
    }
}
