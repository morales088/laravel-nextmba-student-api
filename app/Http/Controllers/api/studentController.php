<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
}
