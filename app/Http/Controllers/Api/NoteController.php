<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\NoteRequest;
use App\Models\Note;

/**
 * API controller for Note resource CRUD operations.
 */
class NoteController extends AbstractResourceController
{
    /**
     * @var class-string<Note>
     */
    protected string $modelClass = Note::class;

    /**
     * @var class-string<NoteRequest>
     */
    protected string $requestClass = NoteRequest::class;
}
