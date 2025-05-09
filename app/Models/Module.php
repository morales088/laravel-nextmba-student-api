<?php

namespace App\Models;

use DB;
use App\Models\ModuleFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Module extends Model
{
    use HasFactory;
    
    protected $guarded = ['id'];
    protected $table = 'modules';

    public static function getModules($userId, $course_id, $modules_type = 'live'){
        
        if($modules_type == 'live'){

            // $modules = DB::SELECT("select DISTINCT m.*, c.name course_name, c.price course_price
            //                             from students s
            //                             left join studentcourses sc ON sc.studentId = s.id
            //                             left join courses c on c.id = sc.courseId
            //                             left join modules m ON m.courseId = c.id and sc.courseId = m.courseId
            //                             where m.broadcast_status = 2 and m.status = 2 and c.status <> 0 and sc.status <> 0 
            //                             and s.id = $userId and c.id = $course_id");


            
            $student_courses = DB::TABLE('studentcourses as sc')
                                    ->leftJoin('courses as c', 'c.id', '=', 'sc.courseId')
                                    ->where('sc.studentId', $userId)
                                    ->where('sc.status', 1)
                                    ->pluck('sc.courseId')
                                    ->toArray();

            // dd($student_courses, implode(',', $student_courses));
            $courses = implode(',', $student_courses);

            $modules = DB::TABLE('courses as c')
                                ->leftJoin('modules as m', 'c.id', '=', 'm.courseId')
                                ->where('c.id', $course_id)
                                ->where('c.status', '<>', 0)
                                ->where('m.status', 2)
                                ->whereIn('m.broadcast_status', [2])
                                ->select('m.*', 'c.name as course_name', DB::RAW("IF(c.paid = 0, true, IF(c.id IN ($courses), true, false ) ) has_access"))
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
                
                    $value->module_files = ModuleFile::where('status', '<>', 0)
                        ->where('moduleId', $value->id)
                        ->get();
                }

            }

        }elseif($modules_type == 'upcoming'){

            // $modules = DB::SELECT("select DISTINCT m.*, c.name course_name, c.price course_price
            //                             from student_modules sm
            //                             left join modules m ON m.id = sm.moduleId
            //                             left join studentcourses sc ON sc.courseId = m.courseId and sc.studentId = sm.studentId
            //                             left join courses c on m.courseId = c.id
            //                             where m. status <> 0 and sm.status <> 0 and c.status <> 0 and sc.status <> 0
            //                             and sm.studentId = $userId and m.broadcast_status in (1) and m.status = 2 
            //                             and c.id = $course_id and m.start_date > '".now()."' order by m.start_date asc");

            $modules = DB::SELECT("select DISTINCT m.*, c.name course_name, c.price course_price
                                            from students s
                                            left join studentcourses sc ON sc.studentId = s.id
                                            left join courses c on c.id = sc.courseId
                                            left join modules m ON m.courseId = c.id and sc.courseId = m.courseId
                                            where m.status <> 0 and c.status <> 0 and sc.status <> 0
                                            and s.id = $userId and m.broadcast_status in (1) and m.status = 2 
                                            and c.id = $course_id and m.end_date > '".now()."' order by m.start_date asc");

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
                
                    $value->module_files = ModuleFile::where('status', '<>', 0)
                        ->where('moduleId', $value->id)
                        ->get();
                }
            }
            
        }else{
            $check = Course::where('id', $course_id)->first();
            $userDate = auth('api')->user()->created_at;
            $userLanguage = auth('api')->user()->language;
            
            if($check->paid == 0){
                $modules = DB::SELECT("select DISTINCT m.*, c.name course_name, c.price course_price
                                        from courses c
                                        left join modules m ON m.courseId = c.id
                                        where m.status = 2 and c.status <> 0
                                        and m.broadcast_status in (3,4) and c.id = $course_id
                                        and date(m.start_date) >= date('$userDate') order by m.start_date asc");

            }else{

                $modules = DB::SELECT("select DISTINCT m.*, c.name course_name, c.price course_price,
                                                IF(date(m.start_date) < date(sc.expirationDate), true, false ) has_access
                                                from students s
                                                left join studentcourses sc ON sc.studentId = s.id
                                                left join courses c on c.id = sc.courseId
                                                left join modules m ON m.courseId = c.id and sc.courseId = m.courseId
                                                where m.status = 2 and c.status <> 0 and sc.status <> 0
                                                and s.id = $userId and m.broadcast_status in (3,4) and c.id = $course_id 
                                                and date(m.start_date) >= date(sc.starting) order by m.start_date asc");
                
            }
            
            if($modules){
                foreach ($modules as $key => $value) {
                    $translation = ModelLanguage::where("module_id", $value->id)
                                                ->where('language', $userLanguage)
                                                ->where('status', 1)
                                                ->first();
                    // dd( empty($translation) );                            
                    if(!empty($translation)){
                        $value->description = urldecode($translation->description);
                        $value->name = urldecode($translation->name);
                    }else{
                        $value->description = urldecode($value->description);
                    }

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
                
                    $value->module_files = ModuleFile::where('status', '<>', 0)
                        ->where('moduleId', $value->id)
                        ->get();
                }
            }
        }
        
        return $modules;
    }
}
