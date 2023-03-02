<?php

namespace App\Http\Controllers\api;

use DB;
use App\Models\VideoLibrary;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;

class libraryController extends Controller
{
    public function index(Request $request){

        $user = auth('api')->user();
        $currentPage = $request->query('page', 1);
        $perPage = $request->query('per_page', 10);
        $type = $request->query('type', 1);

        if (empty($request->query('offset'))) {
            $offset = ($currentPage - 1) * $perPage;
        } else {
            $offset = $request->query('offset');
        }
        
        sleep(5); // slowdown the request for set seconds

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
            ->orderBy('category', 'DESC')
            ->orderBy('date', 'DESC');
                            
        $video_libraries = $video_libraries
            ->offset($offset)
            ->limit($perPage)
            ->get();
        
        $totalOrder = VideoLibrary::where('type', $type)
            ->where( function($query) use($user) {
                $query->where('date', '<=', $user->created_at);
                $query->orWhere('category', 'additional lecture');
            })
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

        sleep(5); // slowdown the request for set seconds

        // cache the response
        $cache_key = 'video_library_' .$id;
        $cacheDuration = 30;

        $video_library = Cache::remember($cache_key, $cacheDuration, function () use($id) {
            return VideoLibrary::where('id', $id)->first();
        });

        $files = DB::table('library_files')
            ->where('libraryId', $id)
            ->where('status', '<>', 0)
            ->get();

        return response(["video_library" => $video_library, 'files' => $files], 200);

    }

}
