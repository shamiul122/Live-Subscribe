<?php

namespace App\Http\Controllers\Organizations\Templates;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Organizations\Templates\Concerns\BuildsTemplateOptions;
use App\Http\Requests\Templates\PreviewMessageTemplateRequest;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Services\Templates\MessageTemplateService;
use App\Services\Templates\TemplateRendererService;
use App\Services\Templates\TemplateVariableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MessageTemplatePreviewController extends Controller
{
    use BuildsTemplateOptions;

    public function __construct(
        private readonly MessageTemplateService $templates,
        private readonly TemplateRendererService $renderer,
        private readonly TemplateVariableService $variables,
    ) {}

    public function show(Organization $organization, MessageTemplate $messageTemplate): Response
    {
        Gate::authorize('message_template.view', $organization);

        $model = $this->templates->find($organization, $messageTemplate->id);

        return Inertia::render('organizations/message-templates/Preview', [
            'organization' => $this->organizationSummary($organization),
            'template' => $this->templates->toArray($model),
            'variables' => $this->variableOptions(),
            'sample_values' => $this->variables->sampleValues(),
        ]);
    }

    public function render(PreviewMessageTemplateRequest $request, Organization $organization): JsonResponse
    {
        $validated = $request->validated();

        $values = $this->renderer->normalizeValues(
            is_array($validated['values'] ?? null) ? $validated['values'] : [],
        );

        $rendered = $this->renderer->renderTemplate(
            (string) $validated['content'],
            $validated['subject'] ?? null,
            $values,
        );

        return response()->json([
            'subject' => $rendered['subject'],
            'content' => $rendered['content'],
            'used_variables' => $rendered['used_variables'],
            'unknown_variables' => $rendered['unknown_variables'],
            'values' => $values,
        ]);
    }
}
