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

        if (empty($request->query('offset'))) {
            $offset = ($currentPage - 1) * $perPage;
        } else {
            $offset = $request->query('offset');
        }
        // dd($user->created_at);
        $video_libraries = VideoLibrary::query();

        $video_libraries = $video_libraries
                            ->where('date', '<=', $user->created_at)
                            ->where('status', 1)
                            ->where('broadcast_status', 1);
                            // ->orderBy('date', 'DESC');
                            // ->get();
                            
        $video_libraries = $video_libraries->offset($offset)
                                ->limit($perPage)
                                ->orderBy('date', 'DESC')
                                ->get();
        
        $totalOrder = VideoLibrary::where('status', 1)->where('broadcast_status', 1)->count();
        
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

        return response(["video_library" => $video_library], 200);

    }

}
