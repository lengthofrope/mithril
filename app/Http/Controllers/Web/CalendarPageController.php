<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Handles the dedicated calendar page rendering.
 *
 * Displays all calendar events for the current week with full detail,
 * grouped by day, including resource linking actions.
 */
class CalendarPageController extends Controller
{
    /**
     * Display the calendar events page.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $userTz = $request->user()->getEffectiveTimezone();

        $calendarEvents = CalendarEvent::query()
            ->with('links')
            ->startingFrom(now($userTz)->startOfDay()->utc())
            ->until(now($userTz)->endOfWeek()->utc())
            ->orderBy('start_at')
            ->get();

        $isMicrosoftConnected = $request->user()->hasMicrosoftConnection();

        return view('pages.calendar', [
            'title' => 'Calendar',
            'calendarEvents' => $calendarEvents,
            'isMicrosoftConnected' => $isMicrosoftConnected,
            'userTimezone' => $userTz,
        ]);
    }
}
