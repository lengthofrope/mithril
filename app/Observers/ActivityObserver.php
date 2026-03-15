<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Bila;
use App\Models\FollowUp;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Listens to Eloquent model update events and logs system activity entries
 * for fields that are configured as tracked on each supported model.
 *
 * Supported models and their tracked fields:
 *  - Task:     status, priority
 *  - FollowUp: status, snoozed_until
 *  - Bila:     is_done
 */
class ActivityObserver
{
    /**
     * Tracked fields per model class, mapped to their value type for formatting.
     *
     * Supported types:
     *  - 'enum'    — string-backed enum; use ->value for display and storage
     *  - 'boolean' — boolean field
     *  - 'date'    — nullable date string
     *
     * @var array<class-string, array<string, string>>
     */
    private array $trackedFields = [
        Task::class => [
            'status'   => 'enum',
            'priority' => 'enum',
        ],
        FollowUp::class => [
            'status'        => 'enum',
            'snoozed_until' => 'date',
        ],
        Bila::class => [
            'is_done' => 'boolean',
        ],
    ];

    /**
     * Handle the model "updated" event.
     *
     * Inspects each tracked field for the given model and creates one
     * system activity entry per field that has genuinely changed.
     *
     * @param Model $model
     * @return void
     */
    public function updated(Model $model): void
    {
        if (! Auth::check()) {
            return;
        }

        $fields = $this->trackedFields[get_class($model)] ?? [];

        foreach ($fields as $field => $type) {
            if (! $model->wasChanged($field)) {
                continue;
            }

            $oldRaw = $model->getOriginal($field);
            $newRaw = $model->getAttribute($field);

            $old = $this->resolveScalarValue($oldRaw, $type);
            $new = $this->resolveScalarValue($newRaw, $type);

            if ($old === $new) {
                continue;
            }

            $action = $this->resolveAction($field);
            $body   = $this->buildBody($field, $type, $old, $new);

            if (! method_exists($model, 'logSystemEvent')) {
                continue;
            }

            $model->logSystemEvent($body, $action, [
                $field => [
                    'old' => $old,
                    'new' => $new,
                ],
            ]);
        }
    }

    /**
     * Normalize a raw attribute value to its scalar representation
     * suitable for metadata storage and human-readable display.
     *
     * @param mixed  $value
     * @param string $type
     * @return string|bool|null
     */
    private function resolveScalarValue(mixed $value, string $type): string|bool|null
    {
        if ($value instanceof UnitEnum) {
            return $value->value;
        }

        if ($type === 'boolean') {
            return (bool) $value;
        }

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    /**
     * Derive the action identifier string from a field name.
     *
     * @param string $field
     * @return string
     */
    private function resolveAction(string $field): string
    {
        return match ($field) {
            'is_done'       => 'completion_changed',
            'snoozed_until' => 'snoozed_until_changed',
            default         => $field . '_changed',
        };
    }

    /**
     * Build a human-readable description of the field change.
     *
     * @param string           $field
     * @param string           $type
     * @param string|bool|null $old
     * @param string|bool|null $new
     * @return string
     */
    private function buildBody(string $field, string $type, string|bool|null $old, string|bool|null $new): string
    {
        return match ($type) {
            'boolean' => $this->buildBooleanBody($field, (bool) $new),
            'date'    => $this->buildDateBody($field, $new),
            default   => $this->buildEnumBody($field, $old, $new),
        };
    }

    /**
     * Build the body string for an enum field change.
     *
     * @param string           $field
     * @param string|bool|null $old
     * @param string|bool|null $new
     * @return string
     */
    private function buildEnumBody(string $field, string|bool|null $old, string|bool|null $new): string
    {
        $label = ucfirst(str_replace('_', ' ', $field));

        return "{$label} changed: {$old} \u{2192} {$new}";
    }

    /**
     * Build the body string for a boolean field change.
     *
     * @param string $field
     * @param bool   $newValue
     * @return string
     */
    private function buildBooleanBody(string $field, bool $newValue): string
    {
        $label = str_replace('_', ' ', $field);

        return $newValue
            ? "Marked as {$label}"
            : "Unmarked as {$label}";
    }

    /**
     * Build the body string for a nullable date field change.
     *
     * @param string           $field
     * @param string|bool|null $newValue
     * @return string
     */
    private function buildDateBody(string $field, string|bool|null $newValue): string
    {
        $label = ucfirst(str_replace('_', ' ', $field));

        if ($newValue === null) {
            return "{$label} removed";
        }

        return "Snoozed until {$newValue}";
    }
}
