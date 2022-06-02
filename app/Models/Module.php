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

            $modules = COLLECT(\DB::SELECT("select m.*, c.name course_name
                                        from student_modules sm
                                        left join modules m ON m.id = sm.moduleId
                                        left join courses c on m.courseId = c.id
                                        where m.broadcast_status = 2 and m. status <> 0 and sm.status <> 0 and c.status <> 0 and
                                        sm.studentId = $userId and c.id = $course_id"))->first();
            
            if($modules){
                $modules->topics = DB::SELECT("select t.moduleId, s.*, sr.role,
                            (CASE WHEN sr.role = 1 THEN 'main' WHEN sr.role = 2 THEN 'guest' END) speaker_role
                            from topics t
                            left join speaker_roles sr ON t.id = sr.topicId
                            left join speakers s on t.speakerId = s.id
                            where t.status <> 0 and sr.status <> 0 and s.status <> 0
                            and t.moduleId = $modules->id");
            }

        }elseif($modules_type == 'upcoming'){

            $modules = COLLECT(\DB::SELECT("select m.*, c.name course_name
                                        from student_modules sm
                                        left join modules m ON m.id = sm.moduleId
                                        left join courses c on m.courseId = c.id
                                        where m. status <> 0 and sm.status <> 0 and c.status <> 0
                                        and sm.studentId = $userId and m.broadcast_status = 1 and c.id = $course_id and m.start_date > '".now()."'"))->first();

            if($modules){
                $modules->topics = DB::SELECT("select t.moduleId, s.*, sr.role,
                                        (CASE WHEN sr.role = 1 THEN 'main' WHEN sr.role = 2 THEN 'guest' END) speaker_role
                                        from topics t
                                        left join speaker_roles sr ON t.id = sr.topicId
                                        left join speakers s on t.speakerId = s.id
                                        where t.status <> 0 and sr.status <> 0 and s.status <> 0
                                        and t.moduleId = $modules->id");
            }
            
        }else{
            $modules = COLLECT(\DB::SELECT("select m.*, c.name course_name
                                        from student_modules sm
                                        left join modules m ON m.id = sm.moduleId
                                        left join courses c on m.courseId = c.id
                                        where m. status <> 0 and sm.status <> 0 and c.status <> 0
                                        and sm.studentId = $userId and m.broadcast_status not in (1,2) and c.id = $course_id and m.end_date < '".now()."'"))->first();
            
            if($modules){
                $modules->topics = DB::SELECT("select t.moduleId, s.*, sr.role,
                                        (CASE WHEN sr.role = 1 THEN 'main' WHEN sr.role = 2 THEN 'guest' END) speaker_role
                                        from topics t
                                        left join speaker_roles sr ON t.id = sr.topicId
                                        left join speakers s on t.speakerId = s.id
                                        where t.status <> 0 and sr.status <> 0 and s.status <> 0
                                        and t.moduleId = $modules->id");
            }
        }

        return $modules;
    }
}
