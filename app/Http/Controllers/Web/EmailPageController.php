<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Handles the dedicated mail page rendering.
 *
 * Displays all non-dismissed emails with source filtering and resource linking actions.
 */
class EmailPageController extends Controller
{
    /**
     * Display the mail page.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $isMicrosoftConnected = $request->user()->hasMicrosoftConnection();

        return view('pages.mail', [
            'title' => 'E-mail',
            'isMicrosoftConnected' => $isMicrosoftConnected,
        ]);
    }
}
