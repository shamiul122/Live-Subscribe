<?php

namespace App\Http\Controllers\Organizations\Inbox;

use App\Enums\CommunicationCapability;
use App\Enums\ConversationPriority;
use App\Enums\ConversationStatus;
use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Models\ConnectedAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Models\User;
use App\Services\Communication\CommunicationProviderRegistry;
use App\Services\Inbox\ContactService;
use App\Services\Inbox\ConversationService;
use App\Services\Inbox\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversations,
        private readonly ContactService $contacts,
        private readonly MessageService $messages,
        private readonly CommunicationProviderRegistry $communication,
    ) {
    }

    public function index(Request $request, Organization $organization): Response
    {
        Gate::authorize('inbox.view', $organization);

        $conversations = $this->conversations->list(
            $organization,
            (string) $request->query('search', ''),
            $this->statusFromRequest($request),
            $this->providerFromRequest($request),
        );

        return Inertia::render('organizations/inbox/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'conversations' => $conversations,
            'contacts' => $this->contacts->list($organization, (string) $request->query('search', '')),
            'statuses' => ConversationStatus::values(),
            'priorities' => ConversationPriority::values(),
            'providers' => SocialProvider::values(),
            'permissions' => [
                'manage' => Gate::allows('inbox.manage', $organization),
            ],
        ]);
    }

    public function show(Request $request, Organization $organization, Conversation $conversation): Response
    {
        Gate::authorize('inbox.view', $organization);

        $conversation = $this->conversations->find($organization, $conversation->id);

        return Inertia::render('organizations/inbox/Conversation', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'conversation' => $this->conversations->toArray($conversation),
            'messages' => $this->messages
                ->forConversation($organization, $conversation)
                ->map(fn (Message $message): array => $this->messages->toArray($message))
                ->all(),
            'members' => $organization->members()
                ->get()
                ->map(fn (User $member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'is_owner' => $organization->isOwnedBy($member),
                ])
                ->all(),
            'contacts' => $this->contacts->list($organization),
            'statuses' => ConversationStatus::values(),
            'priorities' => ConversationPriority::values(),
            'providers' => SocialProvider::values(),
            'permissions' => [
                'manage' => Gate::allows('inbox.manage', $organization),
            ],
            'communication' => $this->communicationProps($conversation->connectedAccount),
        ]);
    }

    /**
     * Capability and readiness details for the composer.
     *
     * A disabled capability is surfaced together with the adapter's honest
     * reason instead of offering an action that would always fail.
     *
     * @return array<string, mixed>
     */
    private function communicationProps(?ConnectedAccount $account): array
    {
        $provider = $account !== null ? $this->communication->providerForAccount($account) : null;
        $status = $account !== null && $provider !== null ? $provider->accountStatus($account) : null;

        $capabilities = [];
        $reasons = [];

        foreach (CommunicationCapability::cases() as $capability) {
            $capabilities[$capability->value] = $provider?->supports($capability) ?? false;
            $reasons[$capability->value] = $provider?->capabilityReason($capability);
        }

        return [
            'provider' => $account?->provider->value,
            'configured' => $status->configured ?? false,
            'usable' => $status->usable ?? false,
            'error_code' => $status->errorCode?->value,
            'error_message' => $status->errorMessage,
            'capabilities' => $capabilities,
            'capability_reasons' => $reasons,
            'attachments' => [
                'max_size_kb' => (int) config('communication.attachments.max_size_kb', 10240),
                'max_per_message' => (int) config('communication.attachments.max_per_message', 5),
            ],
        ];
    }

    private function statusFromRequest(Request $request): ?ConversationStatus
    {
        $status = $request->query('status');

        if ($status === null || $status === '') {
            return null;
        }

        return ConversationStatus::from((string) $status);
    }

    private function providerFromRequest(Request $request): ?SocialProvider
    {
        $provider = $request->query('provider');

        if ($provider === null || $provider === '') {
            return null;
        }

        return SocialProvider::from((string) $provider);
    }
}
