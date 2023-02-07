<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DB;

class Module extends Model
{
    use HasFactory;
    
    protected $guarded = ['id'];
    protected $table = 'modules';

    public static function getModules($userId, $course_id, $modules_type = 'live'){
        
        if($modules_type == 'live'){

            $modules = DB::SELECT("select DISTINCT m.*, c.name course_name, c.price course_price
                                        from student_modules sm
                                        left join modules m ON m.id = sm.moduleId
                                        left join studentcourses sc ON sc.courseId = m.courseId and sc.studentId = sm.studentId
                                        left join courses c on m.courseId = c.id
                                        where m.broadcast_status = 2 and m.status = 2 and sm.status <> 0 and c.status <> 0 and sc.status <> 0 and 
                                        sm.studentId = $userId and c.id = $course_id");
                                        
            
            if($modules){

                foreach ($modules as $key => $value) {

                    $value->description = urldecode($value->description);

                    $topics = DB::SELECT("SELECT t.id topic_id, t.moduleId, t.name topic_name, t.video_link topic_video_link, t.description topic_description,
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
                }

            }

        }elseif($modules_type == 'upcoming'){

            $modules = DB::SELECT("select DISTINCT m.*, c.name course_name, c.price course_price
                                        from student_modules sm
                                        left join modules m ON m.id = sm.moduleId
                                        left join studentcourses sc ON sc.courseId = m.courseId and sc.studentId = sm.studentId
                                        left join courses c on m.courseId = c.id
                                        where m. status <> 0 and sm.status <> 0 and c.status <> 0 and sc.status <> 0
                                        and sm.studentId = $userId and m.broadcast_status in (1) and m.status = 2 and c.id = $course_id and m.start_date > '".now()."' order by m.start_date asc");

            if($modules){
                foreach ($modules as $key => $value) {
                    $value->description = urldecode($value->description);

                    $topics = DB::SELECT("SELECT t.id topic_id, t.moduleId, t.name topic_name, t.video_link topic_video_link, t.description topic_description,
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

                }
            }
            
        }else{
            $modules = DB::SELECT("select DISTINCT m.*, c.name course_name, c.price course_price
                                        from student_modules sm
                                        left join students s ON s.id = sm.studentId
                                        left join modules m ON m.id = sm.moduleId
                                        left join studentcourses sc ON sc.courseId = m.courseId and sc.studentId = sm.studentId
                                        left join courses c on m.courseId = c.id
                                        where m.status = 2 and sm.status <> 0 and c.status <> 0 and sc.status <> 0
                                        and sm.studentId = $userId and m.broadcast_status in (3,4) and c.id = $course_id 
                                        and date(m.start_date) >= date(s.created_at) order by m.start_date asc");
            
            if($modules){
                foreach ($modules as $key => $value) {
                    $value->description = urldecode($value->description);

                    $topics = DB::SELECT("SELECT t.id topic_id, t.moduleId, t.name topic_name, t.video_link topic_video_link, t.description topic_description,
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
                }
            }
        }
        
        return $modules;
    }
}
