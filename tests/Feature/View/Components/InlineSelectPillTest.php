<?php

declare(strict_types=1);

use App\Enums\Priority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('inline select pill renders with current value and all options', function () {
    /** @var \Tests\TestCase $this */
    $view = $this->blade(
        '<x-tl.inline-select-pill
            :value="$value"
            :options="$options"
            :color-map="$colorMap"
            endpoint="/api/v1/tasks/1"
            field="priority"
        />',
        [
            'value' => 'high',
            'options' => [
                'urgent' => 'Urgent',
                'high' => 'High',
                'normal' => 'Normal',
                'low' => 'Low',
            ],
            'colorMap' => [
                'urgent' => 'bg-red-50 text-red-600',
                'high' => 'bg-orange-50 text-orange-600',
                'normal' => 'bg-blue-50 text-blue-600',
                'low' => 'bg-gray-100 text-gray-600',
            ],
        ],
    );

    $view->assertSee('High');
    $view->assertSee('Urgent');
    $view->assertSee('Normal');
    $view->assertSee('Low');
});

test('inline select pill renders with status values', function () {
    /** @var \Tests\TestCase $this */
    $view = $this->blade(
        '<x-tl.inline-select-pill
            :value="$value"
            :options="$options"
            :color-map="$colorMap"
            endpoint="/api/v1/tasks/1"
            field="status"
        />',
        [
            'value' => 'open',
            'options' => [
                'open' => 'Open',
                'in_progress' => 'In Progress',
                'waiting' => 'Waiting',
                'done' => 'Done',
            ],
            'colorMap' => [
                'open' => 'bg-blue-50 text-blue-600',
                'in_progress' => 'bg-yellow-50 text-yellow-700',
                'waiting' => 'bg-orange-50 text-orange-600',
                'done' => 'bg-green-50 text-green-600',
            ],
        ],
    );

    $view->assertSee('Open');
    $view->assertSee('In Progress');
    $view->assertSee('Waiting');
    $view->assertSee('Done');
});

test('inline select pill contains alpine data binding for inlineSelect', function () {
    /** @var \Tests\TestCase $this */
    $view = $this->blade(
        '<x-tl.inline-select-pill
            :value="$value"
            :options="$options"
            :color-map="$colorMap"
            endpoint="/api/v1/tasks/1"
            field="priority"
        />',
        [
            'value' => 'normal',
            'options' => [
                'urgent' => 'Urgent',
                'high' => 'High',
                'normal' => 'Normal',
                'low' => 'Low',
            ],
            'colorMap' => [
                'urgent' => 'bg-red-50 text-red-600',
                'high' => 'bg-orange-50 text-orange-600',
                'normal' => 'bg-blue-50 text-blue-600',
                'low' => 'bg-gray-100 text-gray-600',
            ],
        ],
    );

    $view->assertSee('x-data', false);
    $view->assertSee('inlineSelect', false);
});

test('inline select pill passes endpoint and field to alpine component', function () {
    /** @var \Tests\TestCase $this */
    $view = $this->blade(
        '<x-tl.inline-select-pill
            :value="$value"
            :options="$options"
            :color-map="$colorMap"
            endpoint="/api/v1/tasks/42"
            field="status"
        />',
        [
            'value' => 'open',
            'options' => ['open' => 'Open', 'done' => 'Done'],
            'colorMap' => ['open' => 'bg-blue-50', 'done' => 'bg-green-50'],
        ],
    );

    $view->assertSee('/api/v1/tasks/42', false);
    $view->assertSee('status', false);
});
