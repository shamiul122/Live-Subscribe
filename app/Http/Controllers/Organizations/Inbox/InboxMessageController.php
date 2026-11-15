<?php

namespace App\Http\Controllers\Organizations\Inbox;

use App\Enums\MessageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inbox\StoreInboxMessageRequest;
use App\Models\Attachment;
use App\Models\ConnectedAccount;
use App\Models\Message;
use App\Models\Organization;
use App\Services\Communication\Attachments\AttachmentService;
use App\Services\Communication\DTOs\OutboundMessageRequest;
use App\Services\Communication\OutboundMessageService;
use App\Services\Inbox\ConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class InboxMessageController extends Controller
{
    public function __construct(
        private readonly OutboundMessageService $outbound,
        private readonly ConversationService $conversations,
        private readonly AttachmentService $attachments,
    ) {
    }

    public function store(StoreInboxMessageRequest $request, Organization $organization): RedirectResponse
    {
        $data = $request->validated();

        $conversation = $this->conversations->find($organization, (int) $data['conversation_id']);

        $connectedAccount = ConnectedAccount::query()
            ->where('organization_id', $organization->id)
            ->find((int) $data['connected_account_id']);

        if ($connectedAccount === null) {
            throw ValidationException::withMessages([
                'connected_account_id' => 'The connected account does not belong to this organization.',
            ]);
        }

        $files = $request->file('attachments', []);
        $files = is_array($files) ? array_values($files) : [$files];

        /** @var array<int, Attachment> $stored */
        $stored = $files !== []
            ? $this->attachments->storeMany($organization, $request->user(), $files)
            : [];

        $body = trim((string) ($data['body'] ?? ''));

        try {
            $result = $this->outbound->queue(new OutboundMessageRequest(
                organization: $organization,
                conversation: $conversation,
                connectedAccount: $connectedAccount,
                actor: $request->user(),
                body: $body,
                messageType: $this->messageType($body, $stored),
                attachmentIds: array_map(fn (Attachment $attachment): int => $attachment->id, $stored),
                idempotencyKey: isset($data['idempotency_key']) ? (string) $data['idempotency_key'] : null,
            ));
        } catch (Throwable $exception) {
            // The message was never persisted, so the freshly stored files are
            // orphans and must not be left behind.
            $this->attachments->deleteMany($stored);

            throw $exception;
        }

        if ($result['job_dispatched'] === false) {
            // An idempotent replay returned an existing message. The uploaded
            // copies were not linked and would otherwise leak.
            $this->attachments->deleteMany($stored);
        }

        return redirect()->route('organizations.inbox.show', [
            'organization' => $organization->slug,
            'conversation' => $conversation->id,
        ]);
    }

    public function retry(Request $request, Organization $organization, Message $message): RedirectResponse
    {
        Gate::authorize('inbox.manage', $organization);

        if ($message->organization_id !== $organization->id) {
            abort(404);
        }

        $this->outbound->retry($request->user(), $organization, $message);

        return redirect()->route('organizations.inbox.show', [
            'organization' => $organization->slug,
            'conversation' => $message->conversation_id,
        ]);
    }

    /**
     * @param  array<int, Attachment>  $attachments
     */
    private function messageType(string $body, array $attachments): MessageType
    {
        if ($body !== '' || $attachments === []) {
            return MessageType::Text;
        }

        $mime = $attachments[0]->mime_type;

        return match (true) {
            str_starts_with($mime, 'image/') => MessageType::Image,
            str_starts_with($mime, 'video/') => MessageType::Video,
            str_starts_with($mime, 'audio/') => MessageType::Audio,
            default => MessageType::Document,
        };
    }
}
