<?php

namespace Tests\Feature\Templates;

use App\Enums\MessageTemplateCategory;
use App\Enums\MessageTemplateStatus;
use App\Enums\SocialProvider;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\Templates\MessageTemplateService;
use App\Services\Templates\TemplateRendererService;
use App\Services\Templates\TemplateVariableService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesOrganizations;
use Tests\TestCase;

class MessageTemplateManagementTest extends TestCase
{
    use CreatesOrganizations, RefreshDatabase;

    public function test_owner_can_create_a_template_and_variables_are_detected(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $template = app(MessageTemplateService::class)->create($owner, $organization, $this->payload([
            'content' => 'Hi {{customer_name}}, your order {{order_number}} shipped.',
        ]));

        $this->assertSame($organization->id, $template->organization_id);
        $this->assertSame($owner->id, $template->created_by_id);
        $this->assertSame(MessageTemplateStatus::Draft, $template->status);
        $this->assertSame(['customer_name', 'order_number'], $template->variables);
    }

    public function test_template_is_scoped_to_the_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $otherOrganization = $this->createOrganization($owner, 'Other');
        $template = MessageTemplate::factory()->create(['organization_id' => $otherOrganization->id]);

        $this->expectException(ModelNotFoundException::class);

        app(MessageTemplateService::class)->find($organization, $template->id);
    }

    public function test_archived_templates_cannot_be_edited_until_restored(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $template = MessageTemplate::factory()->create([
            'organization_id' => $organization->id,
            'status' => MessageTemplateStatus::Archived,
        ]);

        $service = app(MessageTemplateService::class);

        try {
            $service->update($organization, $template, $this->payload(['name' => 'Changed']));
            $this->fail('Archived templates must not be editable.');
        } catch (ValidationException) {
            // expected
        }

        $restored = $service->restore($organization, $template);

        $this->assertSame(MessageTemplateStatus::Draft, $restored->status);

        $updated = $service->update($organization, $template, $this->payload(['name' => 'Changed']));

        $this->assertSame('Changed', $updated->name);
    }

    public function test_reusable_returns_only_active_templates_for_the_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        MessageTemplate::factory()->active()->create(['organization_id' => $organization->id]);
        MessageTemplate::factory()->create(['organization_id' => $organization->id]);
        MessageTemplate::factory()->active()->create(['organization_id' => $this->createOrganization($owner, 'Other')->id]);

        $reusable = app(MessageTemplateService::class)->reusable($organization);

        $this->assertCount(1, $reusable);
    }

    public function test_unknown_variables_are_reported_but_supported_ones_are_not(): void
    {
        $variables = app(TemplateVariableService::class);

        $this->assertSame(['customer_name'], $variables->detect('Hi {{ customer_name }}'));
        $this->assertSame([], $variables->unknown('Hi {{customer_name}}'));
        $this->assertSame(['secret'], $variables->unknown('Hi {{secret}}'));
        $this->assertSame([], $variables->unknown('Not a placeholder {customer_name}'));
    }

    public function test_rendering_uses_supplied_values_then_samples_and_keeps_unknown_tokens(): void
    {
        $renderer = app(TemplateRendererService::class);

        $rendered = $renderer->render(
            'Hi {{customer_name}} from {{company_name}} - {{unknown_token}}',
            ['customer_name' => 'Ada'],
        );

        $this->assertSame('Hi Ada from Acme Inc. - {{unknown_token}}', $rendered);
    }

    public function test_render_template_reports_used_and_unknown_variables(): void
    {
        $renderer = app(TemplateRendererService::class);

        $result = $renderer->renderTemplate(
            'Order {{order_number}} for {{customer_name}} ({{mystery}})',
            'Receipt {{order_number}}',
        );

        $this->assertSame('Receipt ORD-1042', $result['subject']);
        $this->assertSame('Order ORD-1042 for Jane Doe ({{mystery}})', $result['content']);
        $this->assertSame(['order_number', 'customer_name', 'mystery'], $result['used_variables']);
        $this->assertSame(['mystery'], $result['unknown_variables']);
    }

    public function test_normalize_values_ignores_unsupported_keys(): void
    {
        $renderer = app(TemplateRendererService::class);

        $values = $renderer->normalizeValues([
            'customer_name' => 'Grace',
            'malicious' => '<?php echo 1; ?>',
        ]);

        $this->assertSame('Grace', $values['customer_name']);
        $this->assertArrayNotHasKey('malicious', $values);
    }

    public function test_deleting_a_template_soft_deletes_it(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $template = MessageTemplate::factory()->create(['organization_id' => $organization->id]);

        app(MessageTemplateService::class)->delete($organization, $template);

        $this->assertSoftDeleted('message_templates', ['id' => $template->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Welcome message',
            'description' => 'A welcome message',
            'channel' => SocialProvider::Email->value,
            'category' => MessageTemplateCategory::Welcome->value,
            'status' => MessageTemplateStatus::Draft->value,
            'subject' => 'Welcome',
            'content' => 'Hello {{customer_name}}',
            'metadata' => null,
        ], $overrides);
    }
}
