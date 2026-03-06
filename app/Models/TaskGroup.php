<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use App\Models\Traits\HasSortOrder;
use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TaskGroup model for organizing tasks into logical project groups.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $color
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TaskGroup extends Model
{
    use BelongsToUser;
    use HasFactory;
    use HasSortOrder;
    use Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'color',
        'sort_order',
    ];

    /**
     * Fields available for search.
     *
     * @var list<string>
     */
    protected array $searchableFields = ['name', 'description'];

    /**
     * Get all tasks in this group.
     *
     * @return HasMany<Task>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
