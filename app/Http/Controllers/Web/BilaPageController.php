<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bila;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Handles bila (1-on-1 meeting) page rendering.
 *
 * Provides an overview of upcoming and past bilas, and a detail page
 * with prep items and notes for a single bila session.
 */
class BilaPageController extends Controller
{
    /**
     * Display all bilas split into upcoming and past groups.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $teamMemberId = $request->get('team_member_id');

        $baseQuery = fn () => Bila::query()
            ->when($teamMemberId, fn ($q) => $q->where('team_member_id', $teamMemberId))
            ->with(['teamMember', 'prepItems']);

        $upcoming = $baseQuery()
            ->whereDate('scheduled_date', '>=', now()->toDateString())
            ->orderBy('scheduled_date')
            ->get();

        $past = $baseQuery()
            ->whereDate('scheduled_date', '<', now()->toDateString())
            ->orderByDesc('scheduled_date')
            ->get();

        return view('pages.bilas.index', [
            'title' => "Bila's",
            'upcoming' => $upcoming,
            'past' => $past,
            'selectedTeamMemberId' => $teamMemberId,
        ]);
    }

    /**
     * Display the detail page for a single bila with its prep items and notes.
     *
     * @param Bila $bila
     * @return View
     */
    public function show(Bila $bila): View
    {
        $bila->load(['teamMember', 'prepItems']);

        return view('pages.bilas.show', [
            'title' => 'Bila — ' . $bila->teamMember->name,
            'bila' => $bila,
        ]);
    }
}
