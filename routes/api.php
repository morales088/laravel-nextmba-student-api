<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\AffiliateController;

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
    Route::get("/verify", "api\authorizationController@verifyToken");
    Route::middleware("api_token")->post("/admin-login", "api\authorizationController@adminAccessLogin");
});


Route::prefix("/student")->group( function (){
    
    

    Route::middleware("auth:api")->get("/courses/all_type", "api\studentController@allCourses");
    Route::middleware("auth:api")->get("/courses/{id?}", "api\studentController@getCourses");
    Route::middleware("auth:api")->get("/courses/by_type/{course_type?}", "api\studentController@getCoursesByType");
    Route::middleware("auth:api")->get("/course-progress/{course_id?}", "api\studentController@courseProgress");

    Route::middleware("auth:api")->put("/module/status", "api\studentController@updateStudentModule");
    Route::middleware("auth:api")->get("/module/{moduleId?}", "api\studentController@getModule");
    Route::middleware("auth:api")->get("/modules/by_type/{course_id}/{module_type?}", "api\studentController@getModulesByType");
    Route::middleware("auth:api")->get("/modules/all", "api\studentController@getAllModule");
    
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
    Route::middleware("auth:api")->post("/message/delete", "api\ChatController@delete");
    
    
    Route::middleware("auth:api")->get("/settings", "api\studentController@getStudentSettings");
    Route::middleware("auth:api")->post("/settings", "api\studentController@updateStudentSettings");
    
    Route::middleware("auth:api")->post("/issue/email", "api\studentController@emailIssue");
    
    
    Route::post("/forgot_password", "api\studentController@forgotPasword");
    Route::post("/confirm_password", "api\studentController@updatePassword");
    
    
    
    Route::middleware("auth:api")->post("/stream/watch", "api\streamController@watchReplay");
    Route::middleware("auth:api")->get("/module/streams/{module_id}", "api\studentController@getModuleStreams");
    Route::middleware("auth:api")->get("/topic/replay/{module_id}", "api\studentController@getReplay");
    
    Route::middleware("auth:api")->get("/library", "api\libraryController@index");
    Route::middleware("auth:api")->get("/library/{id}", "api\libraryController@perlLibrary");
    
    Route::get('/affiliate', 'api\AffiliateController@affiliateApplication');
    
    Route::middleware("auth:api")->get('/download-schedule', 'api\CalendarController@downloadSchedule');
});

Route::prefix("/affiliate")->controller(AffiliateController::class)->group( function() {
    Route::post("/apply", "applyAffiliate");
    Route::put("/update-code", "updateAffiliateCode");
    
    Route::get("/payments", "getAffiliatePayments");
    Route::post("/withdraw-method", "withdrawMethod");
    
    Route::get("/withdraws", "getWithdraws");
    Route::get("/withdrawals_info", "getWithdrawalsInfo");
    Route::post("/request_withdrawal", "requestWithdrawal");
});