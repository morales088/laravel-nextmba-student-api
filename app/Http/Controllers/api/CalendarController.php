<?php

namespace App\Http\Controllers\api;

use App\Models\Module;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Spatie\IcalendarGenerator\Components\Event;
use Spatie\IcalendarGenerator\Components\Calendar;

class CalendarController extends Controller
{
    public function downloadSchedule() {

        $modules = Module::where('status', 2)
            ->where('broadcast_status', 1)
            ->orderBy('start_date', 'asc')
            ->get();
        
        // create new iCal calendar
        $calendar = Calendar::create('NextMBA Course Schedule');

        foreach ($modules as $module) {
            // create new event for each module
            $event = Event::create()
                ->name($module->name)
                ->description(strip_tags(urldecode($module->description)))
                ->startsAt(new \DateTime($module->start_date))
                ->endsAt(new \DateTime($module->end_date));
            
            // add the event to the calendar
            $calendar->event($event);
        }

        // convert the calendar to an iCal file
        $iCal = $calendar->get();

        // set the content type to 'text/calendar'
        $headers = array(
            'Content-Type' => 'text/calendar',
            'Content-Disposition' => 'attachment; filename="schedule.ics"'
        );

        return response($iCal, 200 ,$headers);
    }
}
