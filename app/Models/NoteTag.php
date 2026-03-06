<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NoteTag model for tagging notes with keywords.
 *
 * @property int $id
 * @property int $note_id
 * @property string $tag
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class NoteTag extends Model
{
    use BelongsToUser;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'note_id',
        'tag',
    ];

    /**
     * Get the note this tag belongs to.
     *
     * @return BelongsTo<Note, NoteTag>
     */
    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }
}
