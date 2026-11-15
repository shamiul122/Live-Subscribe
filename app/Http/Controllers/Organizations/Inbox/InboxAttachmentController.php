<?php

namespace App\Http\Controllers\Organizations\Inbox;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inbox\StoreInboxAttachmentRequest;
use App\Models\Attachment;
use App\Models\Message;
use App\Models\Organization;
use App\Services\Communication\Attachments\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InboxAttachmentController extends Controller
{
    public function __construct(private readonly AttachmentService $attachments)
    {
    }

    public function store(StoreInboxAttachmentRequest $request, Organization $organization): RedirectResponse
    {
        $data = $request->validated();
        $file = $request->file('file');

        if ($file === null) {
            return redirect()->back()->withErrors(['file' => 'No file was provided.']);
        }

        $message = null;

        if (isset($data['message_id'])) {
            $message = Message::query()
                ->where('organization_id', $organization->id)
                ->find((int) $data['message_id']);

            if ($message === null) {
                abort(404);
            }
        }

        $this->attachments->store($organization, $request->user(), $file, $message);

        return redirect()->back();
    }

    public function download(Request $request, Organization $organization, Attachment $attachment): StreamedResponse
    {
        Gate::authorize('inbox.view', $organization);

        if ($attachment->organization_id !== $organization->id) {
            abort(404);
        }

        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->storage_path)) {
            abort(404);
        }

        return $disk->download($attachment->storage_path, $attachment->original_name);
    }
}
