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
    
    

    Route::middleware("auth:api")->get("/courses/all_type", "api\studentController@allCourses");
    Route::middleware("auth:api")->get("/courses/{id?}", "api\studentController@getCourses");
    Route::middleware("auth:api")->get("/courses/by_type/{course_type?}", "api\studentController@getCoursesByType");

    Route::middleware("auth:api")->put("/module/status", "api\studentController@updateStudentModule");
    Route::middleware("auth:api")->get("/module/{moduleId?}", "api\studentController@getModule");
    Route::middleware("auth:api")->get("/modules/by_type/{course_id}/{module_type?}", "api\studentController@getModulesByType");

    Route::middleware("auth:api")->get("/modules/past/{course_id?}", "api\studentController@getuPastModules");
    Route::middleware("auth:api")->get("/modules/live", "api\studentController@getLiveModules");
    Route::middleware("auth:api")->get("/modules/upcoming", "api\studentController@getUpcomingModules");
    Route::middleware("auth:api")->get("/home", "api\studentController@modulePerCourse");
    Route::middleware("auth:api")->get("/", "api\studentController@getStudentInfo");

    Route::middleware("auth:api")->put("/update", "api\studentController@updateStudent");
    Route::middleware("auth:api")->put("/password", "api\studentController@updatePasword");

    Route::middleware("auth:api")->get("/payment/{id}", "api\studentController@getPayment");
    Route::middleware("auth:api")->get("/billing", "api\studentController@getBilling");
    Route::middleware("auth:api")->post("/refund", "api\studentController@refund");

    Route::middleware("auth:api")->get("/gift", "api\giftController@getGift");
    Route::middleware("auth:api")->post("/gift/send", "api\giftController@sendGift2");
    // Route::middleware("auth:api")->post("/gift/send", "api\giftController@sendGift");
    Route::post("/gift/register", "api\giftController@register");


    Route::middleware("auth:api")->post("/message/send", "api\ChatController@send");
    Route::middleware("auth:api")->post("/message/subscribe", "api\ChatController@subscribe");
    
    
    Route::middleware("auth:api")->get("/settings", "api\studentController@getStudentSettings");
    Route::middleware("auth:api")->post("/settings", "api\studentController@updateStudentSettings");

    Route::middleware("auth:api")->post("/issue/email", "api\studentController@emailIssue");

    
    Route::post("/forgot_password", "api\studentController@forgotPasword");
    Route::get("/confirm_password", "api\studentController@confirmPassword");
    

});