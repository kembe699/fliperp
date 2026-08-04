<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessageAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatAttachmentController extends Controller
{
    public function download(ChatMessageAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment->message->conversation);

        return Storage::disk('public')->download($attachment->file_path, $attachment->file_name);
    }
}
