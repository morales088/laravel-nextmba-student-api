<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });


Route::prefix("/user")->group( function (){
    Route::post("/login", "api\authorizationController@personalAccessLogin");
});


Route::prefix("/student")->group( function (){
    
    

    Route::middleware("auth:api")->get("/courses/{id?}", "api\studentController@getCourses");
    Route::middleware("auth:api")->get("/courses/by_type/{course_type?}", "api\studentController@getCoursesByType");

    Route::middleware("auth:api")->get("/modules/by_type/{course_id}/{module_type?}", "api\studentController@getModulesByType");

    Route::middleware("auth:api")->get("/modules/past/{course_id?}", "api\studentController@getuPastModules");
    Route::middleware("auth:api")->get("/modules/live", "api\studentController@getLiveModules");
    Route::middleware("auth:api")->get("/modules/upcoming", "api\studentController@getUpcomingModules");
    Route::middleware("auth:api")->get("/", "api\studentController@getStudentInfo");


});