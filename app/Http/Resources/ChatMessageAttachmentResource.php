<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'mime_type' => $this->mime_type,
            // Relative — the frontend hits this through its authenticated
            // axios instance (blob-fetch pattern), not a plain <a href>,
            // since a raw browser navigation wouldn't carry the bearer
            // token needed to pass the participant-authorization check.
            'download_path' => "/chat/attachments/{$this->id}/download",
        ];
    }
}
