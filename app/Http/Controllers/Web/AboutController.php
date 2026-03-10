<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Handles the about page rendering.
 *
 * Parses the changelog file to display version history and the current version.
 */
class AboutController extends Controller
{
    /**
     * Display the about page with changelog and current version.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $changelog = $this->parseChangelog();

        return view('pages.about.index', [
            'title' => 'About',
            'currentVersion' => $changelog['currentVersion'],
            'releases' => $changelog['releases'],
        ]);
    }

    /**
     * Parse the CHANGELOG.md file into structured release data.
     *
     * @return array{currentVersion: string, releases: array<int, array{version: string, date: string, sections: array<string, list<string>>}>}
     */
    private function parseChangelog(): array
    {
        $path = base_path('CHANGELOG.md');
        $content = file_exists($path) ? file_get_contents($path) : '';

        $releases = [];
        $currentVersion = 'Unknown';
        $currentRelease = null;
        $currentSection = null;

        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^## \[(.+?)\]\s*[-–—]\s*(.+)$/', $line, $matches)) {
                if ($currentRelease !== null) {
                    $releases[] = $currentRelease;
                }

                $version = $matches[1];
                $date = trim($matches[2]);

                $currentRelease = [
                    'version' => $version,
                    'date' => $date,
                    'sections' => [],
                ];

                if ($currentVersion === 'Unknown') {
                    $currentVersion = $version;
                }

                $currentSection = null;
                continue;
            }

            if (preg_match('/^### (.+)$/', $line, $matches)) {
                $currentSection = $matches[1];

                if ($currentRelease !== null && !isset($currentRelease['sections'][$currentSection])) {
                    $currentRelease['sections'][$currentSection] = [];
                }

                continue;
            }

            if ($currentRelease !== null && $currentSection !== null && preg_match('/^- (.+)$/', $line, $matches)) {
                $currentRelease['sections'][$currentSection][] = $matches[1];
            }
        }

        if ($currentRelease !== null) {
            $releases[] = $currentRelease;
        }

        return [
            'currentVersion' => $currentVersion,
            'releases' => $releases,
        ];
    }
}
