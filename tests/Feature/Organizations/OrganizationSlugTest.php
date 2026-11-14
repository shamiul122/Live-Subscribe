<?php

namespace Tests\Feature\Organizations;

use App\Models\Organization;
use App\Support\OrganizationSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_slug_from_the_name(): void
    {
        $this->assertSame('acme-inc', OrganizationSlug::unique('Acme Inc'));
    }

    public function test_it_generates_unique_slugs(): void
    {
        Organization::factory()->create(['name' => 'Acme', 'slug' => 'acme']);

        $this->assertSame('acme-2', OrganizationSlug::unique('Acme'));
    }

    public function test_soft_deleted_organization_slugs_remain_reserved(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $organization->delete();

        $this->assertSame('acme-2', OrganizationSlug::unique('Acme'));
    }

    public function test_it_falls_back_to_a_generic_slug(): void
    {
        $this->assertSame('organization', OrganizationSlug::unique('!!!'));
    }
}
