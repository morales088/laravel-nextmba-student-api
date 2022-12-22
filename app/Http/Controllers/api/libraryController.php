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

        $currentPage = $request->query('page', 1);
        $perPage = $request->query('per_page', 10);

        if (empty($request->query('offset'))) {
            $offset = ($currentPage - 1) * $perPage;
        } else {
            $offset = $request->query('offset');
        }
        
        $video_libraries = VideoLibrary::query();

        $video_libraries = $video_libraries->where('status', 1)->where('broadcast_status', 1);

        if(!empty($broadcast_status)) $video_libraries = $video_libraries->where('broadcast_status', $broadcast_status); ;

        $video_libraries = $video_libraries->offset($offset)
                                ->limit($perPage)
                                ->orderBy('id', 'ASC')
                                ->get();

        $totalOrder = VideoLibrary::where('status', 1)->where('broadcast_status', 1)->count();
        
        $videos = new LengthAwarePaginator($video_libraries, $totalOrder, $perPage, $currentPage, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return response(["video_libraries" => $videos], 200);

    }

}
