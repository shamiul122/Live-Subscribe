<?php

namespace App\Http\Controllers\Organizations\Inbox;

use App\Enums\ConversationPriority;
use App\Enums\ConversationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inbox\StoreConversationRequest;
use App\Http\Requests\Inbox\UpdateConversationRequest;
use App\Models\ConnectedAccount;
use App\Models\Conversation;
use App\Models\Organization;
use App\Services\Inbox\ConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ConversationController extends Controller
{
    public function __construct(private readonly ConversationService $conversations)
    {
    }

    public function store(StoreConversationRequest $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('inbox.manage', $organization);

        $data = $request->validated();

        $connectedAccountId = isset($data['connected_account_id']) ? (int) $data['connected_account_id'] : null;

        $connectedAccount = $connectedAccountId === null
            ? null
            : ConnectedAccount::query()
                ->where('organization_id', $organization->id)
                ->find($connectedAccountId);

        if ($connectedAccount === null) {
            throw ValidationException::withMessages([
                'conversation' => 'The selected connected account does not belong to this organization.',
            ]);
        }

        $conversation = $this->conversations->create(
            $request->user(),
            $organization,
            $connectedAccount,
            isset($data['contact_id']) ? (int) $data['contact_id'] : null,
            $data['subject'] ?? null,
            $data['provider_conversation_id'] ?? null,
        );

        return redirect()->route('organizations.inbox.show', [
            'organization' => $organization->slug,
            'conversation' => $conversation->id,
        ]);
    }

    public function update(UpdateConversationRequest $request, Organization $organization, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('inbox.manage', $organization);

        $conversation = $this->conversations->find($organization, $conversation->id);

        $data = $request->validated();

        if (array_key_exists('status', $data)) {
            $this->conversations->updateStatus(
                $request->user(),
                $organization,
                $conversation,
                ConversationStatus::from((string) $data['status']),
            );
        }

        if (array_key_exists('priority', $data)) {
            $this->conversations->updatePriority(
                $request->user(),
                $organization,
                $conversation,
                ConversationPriority::from((string) $data['priority']),
            );
        }

        return redirect()->route('organizations.inbox.show', [
            'organization' => $organization->slug,
            'conversation' => $conversation->id,
        ]);
    }

    public function assign(Request $request, Organization $organization, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('inbox.manage', $organization);

        $conversation = $this->conversations->find($organization, $conversation->id);

        $conversation = $this->conversations->assign(
            $request->user(),
            $organization,
            $conversation,
            $request->input('assigned_to_id') !== null ? (int) $request->input('assigned_to_id') : null,
        );

        return redirect()->route('organizations.inbox.show', [
            'organization' => $organization->slug,
            'conversation' => $conversation->id,
        ]);
    }

    public function markRead(Request $request, Organization $organization, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('inbox.view', $organization);

        $conversation = $this->conversations->find($organization, $conversation->id);

        $this->conversations->markAsRead($request->user(), $organization, $conversation);

        return redirect()->route('organizations.inbox.show', [
            'organization' => $organization->slug,
            'conversation' => $conversation->id,
        ]);
    }
}
