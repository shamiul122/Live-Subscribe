<?php

namespace Tests\Feature\Inbox;

use App\Models\Contact;
use App\Models\Organization;
use App\Models\User;
use App\Services\Inbox\ContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesOrganizations;
use Tests\TestCase;

class InboxContactsTest extends TestCase
{
    use CreatesOrganizations, RefreshDatabase;

    public function test_contact_can_be_created_updated_and_listed_within_the_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $contact = app(ContactService::class)->create($owner, $organization, [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '555-0100',
            'notes' => 'VIP',
        ]);

        $this->assertSame($organization->id, $contact->organization_id);
        $this->assertSame('Jane Doe', $contact->name);

        $found = app(ContactService::class)->find($organization, $contact->id);

        $updated = app(ContactService::class)->update($owner, $organization, $found, [
            'name' => 'Jane Smith',
            'email' => 'jane@new.example',
            'phone' => '555-0199',
            'notes' => null,
        ]);

        $this->assertSame('Jane Smith', $updated->name);
        $this->assertSame('555-0199', $updated->phone);

        $list = app(ContactService::class)->list($organization, 'Jane');

        $this->assertCount(1, $list);
        $this->assertSame('Jane Smith', $list[0]['name']);
    }

    public function test_duplicate_contact_email_is_rejected_within_the_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        app(ContactService::class)->create($owner, $organization, [
            'name' => 'Jane Doe',
            'email' => 'dupe@example.com',
        ]);

        $this->expectException(ValidationException::class);

        app(ContactService::class)->create($owner, $organization, [
            'name' => 'Jane Two',
            'email' => 'dupe@example.com',
        ]);
    }

    public function test_the_same_email_may_be_used_by_different_organizations(): void
    {
        $owner = User::factory()->create();
        $first = $this->createOrganization($owner, 'Acme');
        $second = $this->createOrganization($owner, 'Globex');

        app(ContactService::class)->create($owner, $first, [
            'name' => 'Jane Doe',
            'email' => 'dupe@example.com',
        ]);

        $contact = app(ContactService::class)->create($owner, $second, [
            'name' => 'Jane Doe',
            'email' => 'dupe@example.com',
        ]);

        $this->assertSame($second->id, $contact->organization_id);
        $this->assertSame(2, Contact::count());
    }
}
