<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use App\Models\VideoLibrary;
use DB;

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
        sleep(1); // slowdown the request for set seconds
        $video_libraries = VideoLibrary::query();
        
        // \DB::enableQueryLog();
        $video_libraries = $video_libraries
                            ->where('type', $type)
                            // ->where('date', '<=', $user->created_at)
                            // ->where(function($query) use($user) {
                            //     $query->where('date', '<=', $user->created_at);
                            //     $query->orWhere('category', 'additional lecture');
                            // })
                            ->where('status', 1)
                            ->where('broadcast_status', 1)
                            // ->orderByRaw("CASE category WHEN 'additional lecture' THEN 1 ELSE 2 END");
                            ->orderBy('category', 'DESC')
                            ->orderBy('date', 'DESC');
                            // ->get();
                            
        $video_libraries = $video_libraries->offset($offset)
                                ->limit($perPage)
                                // ->orderBy('category', 'ASC')
                                // ->orderBy('date', 'DESC')
                                // ->orderBy('name', 'ASC')
                                ->get();
        // dd(\DB::getQueryLog());
        
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
        
        $video_library = VideoLibrary::where('id', $id)->first();
        $files = DB::SELECT("SELECT * FROM library_files where libraryId = $id and status <> 0");

        return response(["video_library" => $video_library, 'files' => $files], 200);


    }

}
