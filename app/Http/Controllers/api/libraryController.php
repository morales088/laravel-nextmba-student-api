<?php

namespace App\Http\Controllers\api;

use DB;
use App\Models\VideoLibrary;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Pagination\LengthAwarePaginator;

class libraryController extends Controller
{
    public function index(Request $request){

        $user = auth('api')->user();
        $currentPage = $request->query('page', 1);
        $perPage = $request->query('per_page', 10);
        $type = $request->query('type', 1);
        $offset = $request->query('offset', ($currentPage - 1) * $perPage);
  
        $video_libraries = VideoLibrary::query();
    
        if($type == 1){
            $video_libraries = $video_libraries
                ->where('date', '<=', $user->created_at);
        }
        
        $video_libraries = $video_libraries
            // ->where('date', '<=', $user->created_at)
            ->where('type', $type)
            ->where('status', 1)
            ->where('broadcast_status', 1)
            ->orderBy('date', 'DESC');
                            
        $video_libraries = $video_libraries
            ->offset($offset)
            ->limit($perPage)
            ->get();
        
        $totalOrder = VideoLibrary::where('type', $type)
            ->where('date', '<=', $user->created_at)
            // ->where( function($query) use($user) {
            //     $query->where('date', '<=', $user->created_at);
            //     $query->orWhere('category', 'additional lecture');
            // })
            ->where('status', 1)
            ->where('broadcast_status', 1)
            ->count();
        
        $videos = new LengthAwarePaginator($video_libraries, $totalOrder, $perPage, $currentPage, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return response(["video_libraries" => $videos], 200);
    }

    public function perlLibrary(Request $request, $id){
                
        $request->query->add(['id' => $id]);
        $speaker = $request->validate([
            'id' => 'required|string|exists:video_libraries,id',
        ]);

        $video_library =  VideoLibrary::where('id', $id)->first();

        $files = DB::table('library_files')
            ->where('libraryId', $id)
            ->where('status', '<>', 0)
            ->get();

        return response(["video_library" => $video_library, 'files' => $files], 200);

    }

}
