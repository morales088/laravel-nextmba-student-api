<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        (CASE WHEN m.broadcast_status = 1 THEN 'offline' WHEN m.broadcast_status = 2 THEN 'live' WHEN m.broadcast_status = 3 THEN 'pending_replay' WHEN m.broadcast_status = 4 THEN 'replay' END) broadcast_status
        from modules m
        left join student_modules sm ON m.id = sm.moduleId
        where sm.status <> 0 and m.status <> 0 and m.id = $moduleId and sm.studentId = $userId"))->first();

        $student_module->topics = DB::SELECT("select t.*, s.name speaker_name, s.position speaker_position, s.company speaker_company, s.profile_path speaker_profile_path, s.company_path speaker_company_path
        from student_modules sm
        left join topics t ON sm.moduleId = t.moduleId
        left join speakers s ON s.id = t.speakerId
        where sm.status <> 0 and t.status <> 0 and s.status <> 0 
        and sm.moduleId = $moduleId and sm.studentId = $userId");

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
                                    ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) score_percentage
                                    from courses c
                                    left join modules m ON m.courseId = c.id
                                    left join student_modules sm ON m.id = sm.moduleId
                                    left join studentcourses sc ON c.id = sc.courseId and sc.studentId = sm.studentId
                                    where c.status <> 0 and m.status <> 0 and sm.status <> 0 and sc.status <> 0 and sm.studentId = $userId
                                    group by c.id) c where c.score_percentage < 100");
                                    

        }elseif($request->course_type == 'completed'){
            // complete
            $courses = DB::SELECT("select *
                                    from
                                    (select c.*, sc.starting, sc.expirationDate,
                                    SUM(CASE WHEN sm.status = 1 THEN 1 ELSE 0 END) AS `incomple_modules`,
                                    SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) AS `complete_modules`,
                                    count(sm.id) total_st_modules,
                                    ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) score_percentage
                                    from courses c
                                    left join modules m ON m.courseId = c.id
                                    left join student_modules sm ON m.id = sm.moduleId
                                    left join studentcourses sc ON c.id = sc.courseId and sc.studentId = sm.studentId
                                    where c.status <> 0 and m.status <> 0 and sm.status <> 0 and sc.status <> 0 and sm.studentId = $userId
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
                                                ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) score_percentage
                                                from courses c
                                                left join modules m ON m.courseId = c.id
                                                left join student_modules sm ON m.id = sm.moduleId
                                                left join studentcourses sc ON c.id = sc.courseId and sc.studentId = sm.studentId
                                                where c.status <> 0 and m.status <> 0 and sm.status <> 0 and sc.status <> 0 and sm.studentId = $userId and c.id = $value->id"))->first();
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
        where m.broadcast_status = 2 and m. status <> 0 and sm.status <> 0 and c.status <> 0 and
        sm.studentId = $userId");

        foreach ($live_modules as $key => $value) {
        $value->topics = DB::SELECT("select t.moduleId, s.*, sr.role,
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

        $upcoming_modules = DB::SELECT("select m.*, c.name course_name
                                        from student_modules sm
                                        left join modules m ON m.id = sm.moduleId
                                        left join courses c on m.courseId = c.id
                                        where m. status <> 0 and sm.status <> 0 and c.status <> 0
                                        and sm.studentId = 1 and  m.broadcast_status = $userId and m.start_date > '".now()."'");
                                        
        foreach ($upcoming_modules as $key => $value) {
            $value->topics = DB::SELECT("select t.moduleId, s.*, sr.role,
                                        (CASE WHEN sr.role = 1 THEN 'main' WHEN sr.role = 2 THEN 'guest' END) speaker_role
                                        from topics t
                                        left join speaker_roles sr ON t.id = sr.topicId
                                        left join speakers s on t.speakerId = s.id
                                        where t.status <> 0 and sr.status <> 0 and s.status <> 0
                                        and t.moduleId = $value->id");
        }

        return response(["modules" => $upcoming_modules], 200);
    }

    public static function getCourses(Request $request, $id = 0){
        $userId = auth('api')->user()->id;

        if($id){
            $courses = COLLECT(\DB::SELECT("select c.*,
                                ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) completion_percentage
                                from student_modules sm
                                left join modules m ON m.id = sm.moduleId
                                left join courses c on m.courseId = c.id
                                where m. status <> 0 and sm.status <> 0 and c.status <> 0
                                and sm.studentId = $userId and c.id = $id group by c.id"))->first();
        }else{
            $courses = DB::SELECT("select c.*,
                                ROUND( ( (SUM(CASE WHEN sm.status = 3 THEN 1 ELSE 0 END) / count(sm.id)) * 100 ), 0 ) completion_percentage
                                from student_modules sm
                                left join modules m ON m.id = sm.moduleId
                                left join courses c on m.courseId = c.id
                                where m. status <> 0 and sm.status <> 0 and c.status <> 0
                                and sm.studentId = $userId group by c.id");
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
                                                where m.end_date < '".now()."' and sm.studentId = $userId");
        }
        // dd($courses);
        return response(["courses" => $courses], 200);
    }
}
