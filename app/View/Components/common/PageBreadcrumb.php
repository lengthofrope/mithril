<?php

declare(strict_types=1);

namespace App\View\Components\common;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Renders a hierarchical breadcrumb navigation trail.
 *
 * Accepts either a structured items array or a simple pageTitle string for backward compatibility.
 */
class PageBreadcrumb extends Component
{
    /** @var array<int, array{label: string, url: string|null}> */
    public readonly array $items;

    /**
     * Create a new component instance.
     *
     * @param array<int, array{label: string, url: string|null}>|null $items
     * @param string $pageTitle
     */
    public function __construct(
        ?array $items = null,
        string $pageTitle = 'Page',
    ) {
        $this->items = $items ?? [
            ['label' => 'Home', 'url' => '/'],
            ['label' => $pageTitle, 'url' => null],
        ];
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.common.page-breadcrumb');
    }
}
