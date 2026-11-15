<?php

namespace App\Services\Templates;

use App\Enums\MessageTemplateCategory;
use App\Enums\MessageTemplateStatus;
use App\Enums\SocialProvider;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Organization-scoped message template management.
 *
 * The service owns creation, editing, status changes and (soft) deletion. It
 * never sends a message and never renders content itself: rendering belongs to
 * TemplateRendererService. Organization scoping is enforced here so a template
 * from another organization is a 404 rather than a silent leak.
 */
final class MessageTemplateService
{
    public function __construct(private readonly TemplateVariableService $variables) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(
        Organization $organization,
        ?string $search = null,
        ?string $channel = null,
        ?MessageTemplateCategory $category = null,
        ?MessageTemplateStatus $status = null,
    ): array {
        $templates = MessageTemplate::query()
            ->where('organization_id', $organization->id)
            ->with('createdBy')
            ->when($channel !== null && $channel !== '', fn ($query) => $query->where('channel', $channel))
            ->when($category !== null, fn ($query) => $query->where('category', $category->value))
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->when($search !== null && $search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhere('content', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return $templates
            ->map(fn (MessageTemplate $template): array => $this->toArray($template))
            ->values()
            ->all();
    }

    public function find(Organization $organization, int $templateId): MessageTemplate
    {
        $template = MessageTemplate::query()
            ->where('organization_id', $organization->id)
            ->with('createdBy')
            ->find($templateId);

        if ($template === null) {
            throw new ModelNotFoundException("Message template [{$templateId}] was not found for this organization.");
        }

        return $template;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, Organization $organization, array $data): MessageTemplate
    {
        return DB::transaction(function () use ($actor, $organization, $data): MessageTemplate {
            $template = MessageTemplate::query()->create([
                'organization_id' => $organization->id,
                'created_by_id' => $actor->id,
                ...$this->attributes($data),
            ]);

            return $template->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Organization $organization, MessageTemplate $template, array $data): MessageTemplate
    {
        $this->assertBelongs($organization, $template);
        $this->assertEditable($template);

        return DB::transaction(function () use ($template, $data): MessageTemplate {
            $template->fill($this->attributes($data, $template));
            $template->save();

            return $template->refresh();
        });
    }

    public function archive(Organization $organization, MessageTemplate $template): MessageTemplate
    {
        $this->assertBelongs($organization, $template);

        $template->status = MessageTemplateStatus::Archived;
        $template->save();

        return $template;
    }

    public function restore(Organization $organization, MessageTemplate $template): MessageTemplate
    {
        $this->assertBelongs($organization, $template);

        $template->status = MessageTemplateStatus::Draft;
        $template->save();

        return $template;
    }

    public function delete(Organization $organization, MessageTemplate $template): void
    {
        $this->assertBelongs($organization, $template);

        $template->delete();
    }

    /**
     * Active templates offered to other modules (campaigns, inbox).
     *
     * @return array<int, array<string, mixed>>
     */
    public function reusable(Organization $organization, ?string $channel = null): array
    {
        return MessageTemplate::query()
            ->where('organization_id', $organization->id)
            ->where('status', MessageTemplateStatus::Active->value)
            ->when($channel !== null && $channel !== '', fn ($query) => $query->where('channel', $channel))
            ->orderBy('name')
            ->get()
            ->map(fn (MessageTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'channel' => $template->channel,
                'category' => $template->category->value,
                'category_label' => $template->category->label(),
                'subject' => $template->subject,
                'content' => $template->content,
                'variables' => $template->variables ?? [],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(MessageTemplate $template): array
    {
        $template->loadMissing('createdBy');

        $creator = $template->createdBy;

        return [
            'id' => $template->id,
            'organization_id' => $template->organization_id,
            'name' => $template->name,
            'description' => $template->description,
            'channel' => $template->channel,
            'category' => $template->category->value,
            'category_label' => $template->category->label(),
            'status' => $template->status->value,
            'status_label' => $template->status->label(),
            'subject' => $template->subject,
            'content' => $template->content,
            'variables' => $template->variables ?? [],
            'variable_details' => $this->variables->describe($template->variables ?? []),
            'unknown_variables' => $this->variables->unknown($template->content),
            'metadata' => $template->metadata,
            'created_by_id' => $template->created_by_id,
            'created_by' => $creator instanceof User ? [
                'id' => $creator->id,
                'name' => $creator->name,
            ] : null,
            'created_at' => $template->created_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
            'is_editable' => $template->isEditable(),
            'is_reusable' => $template->isReusable(),
        ];
    }

    /**
     * Normalise a validated payload into model attributes.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, ?MessageTemplate $existing = null): array
    {
        $content = (string) $data['content'];

        $channel = $data['channel'] ?? null;

        if (is_string($channel) && $channel !== '' && SocialProvider::tryFrom($channel) === null) {
            throw ValidationException::withMessages([
                'channel' => 'The selected channel is not supported.',
            ]);
        }

        $category = $existing !== null ? $existing->category : MessageTemplateCategory::Custom;

        if (isset($data['category']) && is_string($data['category'])) {
            $category = MessageTemplateCategory::tryFrom($data['category']) ?? $category;
        }

        $status = $existing !== null ? $existing->status : MessageTemplateStatus::Draft;

        if (isset($data['status']) && is_string($data['status'])) {
            $status = MessageTemplateStatus::tryFrom($data['status']) ?? $status;
        }

        return [
            'name' => (string) $data['name'],
            'description' => $data['description'] ?? null,
            'channel' => is_string($channel) && $channel !== '' ? $channel : null,
            'category' => $category,
            'status' => $status,
            'subject' => $data['subject'] ?? null,
            'content' => $content,
            'variables' => $this->variables->detect($content),
            'metadata' => $data['metadata'] ?? null,
        ];
    }

    private function assertBelongs(Organization $organization, MessageTemplate $template): void
    {
        if ($template->organization_id !== $organization->id) {
            throw new ModelNotFoundException("Message template [{$template->id}] was not found for this organization.");
        }
    }

    private function assertEditable(MessageTemplate $template): void
    {
        if (! $template->isEditable()) {
            throw ValidationException::withMessages([
                'status' => 'Archived templates cannot be edited. Restore the template first.',
            ]);
        }
    }
}
