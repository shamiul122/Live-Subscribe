<?php

namespace Tests\Feature\Organizations;

use App\Actions\Organizations\InviteOrganizationMember;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesOrganizations;
use Tests\TestCase;

class OrganizationInvitationTest extends TestCase
{
    use CreatesOrganizations, RefreshDatabase;

    public function test_an_owner_can_invite_a_member(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $response = $this->actingAs($owner)
            ->post(route('organizations.invitations.store', $organization), [
                'email' => 'Invitee@Example.COM',
                'role' => OrganizationRole::Member->value,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('organizations.show', $organization))
            ->assertSessionHas('invitation_url');

        $invitation = OrganizationInvitation::firstOrFail();

        $this->assertSame('invitee@example.com', $invitation->email);
        $this->assertSame(OrganizationRole::Member, $invitation->role);
        $this->assertSame($organization->id, $invitation->organization_id);
        $this->assertSame($owner->id, $invitation->invited_by_id);
        $this->assertNull($invitation->accepted_at);
        $this->assertNull($invitation->revoked_at);
        $this->assertTrue($invitation->expires_at->isFuture());

        $token = basename(parse_url((string) session('invitation_url'), PHP_URL_PATH) ?: '');

        $this->assertNotSame('', $token);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertDatabaseMissing('organization_invitations', ['token_hash' => $token]);

        Notification::assertSentOnDemand(
            OrganizationInvitationNotification::class,
            function (OrganizationInvitationNotification $notification, array $channels, object $notifiable): bool {
                return $notifiable->routes['mail'] === 'invitee@example.com';
            },
        );
    }

    public function test_an_admin_can_invite_a_member(): void
    {
        $organization = $this->createOrganization(User::factory()->create());
        $admin = User::factory()->create();

        $this->addMember($organization, $admin, OrganizationRole::Admin);

        $this->actingAs($admin)
            ->post(route('organizations.invitations.store', $organization), [
                'email' => 'new-member@example.com',
                'role' => OrganizationRole::Member->value,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('organizations.show', $organization));

        $this->assertDatabaseHas('organization_invitations', [
            'organization_id' => $organization->id,
            'email' => 'new-member@example.com',
            'role' => OrganizationRole::Member->value,
        ]);
    }

    public function test_an_admin_cannot_invite_another_admin(): void
    {
        $organization = $this->createOrganization(User::factory()->create());
        $admin = User::factory()->create();

        $this->addMember($organization, $admin, OrganizationRole::Admin);

        $this->actingAs($admin)
            ->post(route('organizations.invitations.store', $organization), [
                'email' => 'new-admin@example.com',
                'role' => OrganizationRole::Admin->value,
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseCount('organization_invitations', 0);
    }

    public function test_the_owner_can_invite_another_admin(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)
            ->post(route('organizations.invitations.store', $organization), [
                'email' => 'new-admin@example.com',
                'role' => OrganizationRole::Admin->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('organization_invitations', [
            'email' => 'new-admin@example.com',
            'role' => OrganizationRole::Admin->value,
        ]);
    }

    public function test_members_cannot_invite(): void
    {
        $organization = $this->createOrganization(User::factory()->create());
        $member = User::factory()->create();

        $this->addMember($organization, $member, OrganizationRole::Member);

        $this->actingAs($member)
            ->post(route('organizations.invitations.store', $organization), [
                'email' => 'someone@example.com',
                'role' => OrganizationRole::Member->value,
            ])
            ->assertForbidden();
    }

    public function test_invalid_roles_are_rejected(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)
            ->post(route('organizations.invitations.store', $organization), [
                'email' => 'someone@example.com',
                'role' => 'owner',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseCount('organization_invitations', 0);
    }

    public function test_existing_members_cannot_be_invited(): void
    {
        $organization = $this->createOrganization(User::factory()->create());
        $member = User::factory()->create();
        $this->addMember($organization, $member);

        $this->actingAs($organization->owner)
            ->post(route('organizations.invitations.store', $organization), [
                'email' => $member->email,
                'role' => OrganizationRole::Member->value,
            ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('organization_invitations', 0);
    }

    public function test_duplicate_pending_invitations_are_rejected(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->invite($organization, $owner, 'invitee@example.com');

        $this->actingAs($owner)
            ->post(route('organizations.invitations.store', $organization), [
                'email' => 'invitee@example.com',
                'role' => OrganizationRole::Member->value,
            ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('organization_invitations', 1);
    }

    public function test_a_invited_user_can_view_and_accept_the_invitation(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);

        $invitation = $this->invite(
            $organization,
            $owner,
            'invitee@example.com',
            OrganizationRole::Admin,
        );

        $this->actingAs($invitee)
            ->get(route('invitations.show', ['token' => $invitation->plainToken]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('organizations/Invitations/Show')
                ->where('valid', true)
                ->where('reason', null)
                ->where('invitation.email', 'invitee@example.com')
                ->where('invitation.role', OrganizationRole::Admin->value)
                ->where('invitation.organization_name', $organization->name),
            );

        $this->actingAs($invitee)
            ->post(route('invitations.accept', ['token' => $invitation->plainToken]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('organizations.show', $organization))
            ->assertSessionHas('current_organization_id', $organization->id);

        $membership = $organization->memberships()
            ->where('user_id', $invitee->id)
            ->firstOrFail();

        $this->assertSame(OrganizationRole::Admin, $membership->role);
        $this->assertSame(OrganizationMembershipStatus::Active, $membership->status);
        $this->assertNotNull($membership->joined_at);

        $invitation->refresh();

        $this->assertNotNull($invitation->accepted_at);
        $this->assertSame($invitee->id, $invitation->accepted_by_id);
    }

    public function test_an_invitation_cannot_be_accepted_by_a_different_email_address(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $invitation = $this->invite($organization, $owner, 'invitee@example.com');
        $other = User::factory()->create(['email' => 'other@example.com']);

        $this->actingAs($other)
            ->get(route('invitations.show', ['token' => $invitation->plainToken]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('valid', false)
                ->where('reason', 'This invitation was sent to a different email address.'),
            );

        $this->actingAs($other)
            ->post(route('invitations.accept', ['token' => $invitation->plainToken]))
            ->assertSessionHasErrors('invitation');

        $this->assertDatabaseMissing('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $other->id,
        ]);
    }

    public function test_expired_invitations_cannot_be_accepted(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);

        $plainToken = 'expired-token';
        OrganizationInvitation::factory()->expired()->create([
            'organization_id' => $organization->id,
            'invited_by_id' => $owner->id,
            'email' => 'invitee@example.com',
            'token_hash' => hash('sha256', $plainToken),
        ]);

        $this->actingAs($invitee)
            ->post(route('invitations.accept', ['token' => $plainToken]))
            ->assertSessionHasErrors('invitation');

        $this->assertDatabaseCount('organization_memberships', 1);
    }

    public function test_revoked_invitations_cannot_be_accepted(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);

        $plainToken = 'revoked-token';
        OrganizationInvitation::factory()->revoked()->create([
            'organization_id' => $organization->id,
            'invited_by_id' => $owner->id,
            'email' => 'invitee@example.com',
            'token_hash' => hash('sha256', $plainToken),
        ]);

        $this->actingAs($invitee)
            ->post(route('invitations.accept', ['token' => $plainToken]))
            ->assertSessionHasErrors('invitation');
    }

    public function test_accepting_an_invitation_is_idempotent_for_the_same_user(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = $this->invite($organization, $owner, 'invitee@example.com');

        $this->actingAs($invitee)->post(route('invitations.accept', ['token' => $invitation->plainToken]));
        $this->actingAs($invitee)
            ->post(route('invitations.accept', ['token' => $invitation->plainToken]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('organizations.show', $organization));

        $this->assertSame(
            1,
            $organization->memberships()->where('user_id', $invitee->id)->count(),
        );
    }

    public function test_accepting_an_invitation_reactivates_an_inactive_membership(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);

        $this->addMember(
            $organization,
            $invitee,
            OrganizationRole::Member,
            OrganizationMembershipStatus::Inactive,
        );

        $invitation = $this->invite(
            $organization,
            $owner,
            'invitee@example.com',
            OrganizationRole::Admin,
        );

        $this->actingAs($invitee)
            ->post(route('invitations.accept', ['token' => $invitation->plainToken]))
            ->assertSessionHasNoErrors();

        $membership = $organization->memberships()
            ->where('user_id', $invitee->id)
            ->firstOrFail();

        $this->assertSame(OrganizationMembershipStatus::Active, $membership->status);
        $this->assertSame(OrganizationRole::Admin, $membership->role);
    }

    public function test_invitations_for_deleted_organizations_cannot_be_accepted(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = $this->invite($organization, $owner, 'invitee@example.com');

        $organization->delete();

        $this->actingAs($invitee)
            ->post(route('invitations.accept', ['token' => $invitation->plainToken]))
            ->assertSessionHasErrors('invitation');
    }

    public function test_unknown_invitation_tokens_return_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('invitations.show', ['token' => 'does-not-exist']))
            ->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->post(route('invitations.accept', ['token' => 'does-not-exist']))
            ->assertNotFound();
    }

    public function test_the_owner_and_admins_can_revoke_a_pending_invitation(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $admin = User::factory()->create();
        $this->addMember($organization, $admin, OrganizationRole::Admin);

        $first = $this->invite($organization, $owner, 'one@example.com');
        $second = $this->invite($organization, $owner, 'two@example.com');

        $this->actingAs($owner)
            ->delete(route('organizations.invitations.destroy', [$organization, $first]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('organizations.show', $organization));

        $this->actingAs($admin)
            ->delete(route('organizations.invitations.destroy', [$organization, $second]))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($first->fresh()->revoked_at);
        $this->assertNotNull($second->fresh()->revoked_at);
    }

    public function test_members_cannot_revoke_invitations(): void
    {
        $organization = $this->createOrganization(User::factory()->create());
        $member = User::factory()->create();
        $this->addMember($organization, $member);
        $invitation = $this->invite($organization, $organization->owner, 'invitee@example.com');

        $this->actingAs($member)
            ->delete(route('organizations.invitations.destroy', [$organization, $invitation]))
            ->assertForbidden();

        $this->assertNull($invitation->fresh()->revoked_at);
    }

    public function test_invitations_from_another_organization_cannot_be_revoked(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $otherOrganization = $this->createOrganization($owner, 'Other');
        $invitation = $this->invite($otherOrganization, $owner, 'invitee@example.com');

        $this->actingAs($owner)
            ->delete(route('organizations.invitations.destroy', [$organization, $invitation]))
            ->assertNotFound();
    }

    private function invite(
        Organization $organization,
        User $inviter,
        string $email,
        OrganizationRole $role = OrganizationRole::Member,
    ): OrganizationInvitation {
        return app(InviteOrganizationMember::class)->handle(
            $organization,
            $inviter,
            $email,
            $role,
        );
    }
}
