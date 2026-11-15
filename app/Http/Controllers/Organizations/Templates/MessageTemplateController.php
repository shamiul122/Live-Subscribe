<?php

namespace App\Http\Controllers\Organizations\Templates;

use App\Enums\MessageTemplateCategory;
use App\Enums\MessageTemplateStatus;
use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Organizations\Templates\Concerns\BuildsTemplateOptions;
use App\Http\Requests\Templates\StoreMessageTemplateRequest;
use App\Http\Requests\Templates\UpdateMessageTemplateRequest;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Services\Templates\MessageTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MessageTemplateController extends Controller
{
    use BuildsTemplateOptions;

    public function __construct(private readonly MessageTemplateService $templates) {}

    public function index(Request $request, Organization $organization): Response
    {
        Gate::authorize('message_template.view', $organization);

        $search = (string) $request->query('search', '');
        $channel = $this->channelFromRequest($request);
        $category = $this->categoryFromRequest($request);
        $status = $this->statusFromRequest($request);

        return Inertia::render('organizations/message-templates/Index', [
            'organization' => $this->organizationSummary($organization),
            'templates' => $this->templates->list($organization, $search, $channel, $category, $status),
            'filters' => [
                'search' => $search,
                'channel' => $channel,
                'category' => $category?->value,
                'status' => $status?->value,
            ],
            'categories' => $this->categoryOptions(),
            'statuses' => $this->statusOptions(),
            'channels' => $this->channelOptions(),
            'permissions' => [
                'manage' => Gate::allows('message_template.manage', $organization),
            ],
        ]);
    }

    public function create(Organization $organization): Response
    {
        Gate::authorize('message_template.manage', $organization);

        return Inertia::render('organizations/message-templates/Create', $this->formProps($organization));
    }

    public function store(StoreMessageTemplateRequest $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('message_template.manage', $organization);

        $template = $this->templates->create($request->user(), $organization, $request->validated());

        return redirect()
            ->route('organizations.message-templates.show', [
                'organization' => $organization->slug,
                'message_template' => $template->id,
            ])
            ->with('status', 'The message template was created.');
    }

    public function show(Organization $organization, MessageTemplate $messageTemplate): Response
    {
        Gate::authorize('message_template.view', $organization);

        $model = $this->templates->find($organization, $messageTemplate->id);

        return Inertia::render('organizations/message-templates/Show', [
            'organization' => $this->organizationSummary($organization),
            'template' => $this->templates->toArray($model),
            'variables' => $this->variableOptions(),
            'permissions' => [
                'manage' => Gate::allows('message_template.manage', $organization),
            ],
        ]);
    }

    public function edit(Organization $organization, MessageTemplate $messageTemplate): Response|RedirectResponse
    {
        Gate::authorize('message_template.manage', $organization);

        $model = $this->templates->find($organization, $messageTemplate->id);

        if (! $model->isEditable()) {
            return redirect()
                ->route('organizations.message-templates.show', [
                    'organization' => $organization->slug,
                    'message_template' => $model->id,
                ])
                ->with('status', 'Archived templates cannot be edited. Restore the template first.');
        }

        return Inertia::render('organizations/message-templates/Edit', [
            ...$this->formProps($organization),
            'template' => $this->templates->toArray($model),
        ]);
    }

    public function update(UpdateMessageTemplateRequest $request, Organization $organization, MessageTemplate $messageTemplate): RedirectResponse
    {
        Gate::authorize('message_template.manage', $organization);

        $model = $this->templates->find($organization, $messageTemplate->id);

        $this->templates->update($organization, $model, $request->validated());

        return redirect()
            ->route('organizations.message-templates.show', [
                'organization' => $organization->slug,
                'message_template' => $model->id,
            ])
            ->with('status', 'The message template was updated.');
    }

    public function archive(Organization $organization, MessageTemplate $messageTemplate): RedirectResponse
    {
        Gate::authorize('message_template.manage', $organization);

        $model = $this->templates->find($organization, $messageTemplate->id);

        $this->templates->archive($organization, $model);

        return redirect()
            ->route('organizations.message-templates.show', [
                'organization' => $organization->slug,
                'message_template' => $model->id,
            ])
            ->with('status', 'The message template was archived.');
    }

    public function restore(Organization $organization, MessageTemplate $messageTemplate): RedirectResponse
    {
        Gate::authorize('message_template.manage', $organization);

        $model = $this->templates->find($organization, $messageTemplate->id);

        $this->templates->restore($organization, $model);

        return redirect()
            ->route('organizations.message-templates.show', [
                'organization' => $organization->slug,
                'message_template' => $model->id,
            ])
            ->with('status', 'The message template was restored as a draft.');
    }

    public function destroy(Organization $organization, MessageTemplate $messageTemplate): RedirectResponse
    {
        Gate::authorize('message_template.manage', $organization);

        $model = $this->templates->find($organization, $messageTemplate->id);

        $this->templates->delete($organization, $model);

        return redirect()
            ->route('organizations.message-templates.index', ['organization' => $organization->slug])
            ->with('status', 'The message template was deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(Organization $organization): array
    {
        return [
            'organization' => $this->organizationSummary($organization),
            'categories' => $this->categoryOptions(),
            'statuses' => $this->statusOptions(),
            'channels' => $this->channelOptions(),
            'variables' => $this->variableOptions(),
            'permissions' => [
                'manage' => Gate::allows('message_template.manage', $organization),
            ],
        ];
    }

    private function statusFromRequest(Request $request): ?MessageTemplateStatus
    {
        $status = $request->query('status');

        if (! is_string($status) || $status === '') {
            return null;
        }

        return MessageTemplateStatus::tryFrom($status);
    }

    private function categoryFromRequest(Request $request): ?MessageTemplateCategory
    {
        $category = $request->query('category');

        if (! is_string($category) || $category === '') {
            return null;
        }

        return MessageTemplateCategory::tryFrom($category);
    }

    private function channelFromRequest(Request $request): ?string
    {
        $channel = $request->query('channel');

        if (! is_string($channel) || $channel === '') {
            return null;
        }

        return SocialProvider::tryFrom($channel)?->value;
    }
}
