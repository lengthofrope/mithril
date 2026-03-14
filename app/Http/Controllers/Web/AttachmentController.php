<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Web controller for serving authenticated file downloads via signed URLs.
 */
class AttachmentController extends Controller
{
    /**
     * Stream the attachment file to the authenticated user.
     *
     * The model is resolved manually to ensure SubstituteBindings (which runs
     * before route-level middleware) does not trigger a 404 before the signed
     * middleware can validate the URL signature and return the correct 403.
     * BelongsToUser global scope ensures only the owner can access their record.
     *
     * @param int $attachment
     * @return StreamedResponse
     */
    public function download(int $attachment): StreamedResponse
    {
        $model = Attachment::findOrFail($attachment);

        return Storage::disk($model->disk)->download(
            $model->path,
            $model->filename,
            ['Content-Type' => $model->mime_type],
        );
    }
}
