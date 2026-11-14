<?php

namespace App\Http\Controllers\Organizations;

use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Models\ConnectedAccount;
use App\Models\Organization;
use App\Services\Social\SocialAccountManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Gate;

class ConnectedAccountController extends Controller
{
    public function __construct(private readonly SocialAccountManager $manager)
    {
    }

    public function index(Request $request, Organization $organization): Response
    {
        Gate::authorize('billing.viewSocialAccounts', $organization);

        $accounts = $this->manager->list($organization);

        return Inertia::render('organizations/connected-accounts/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'connected_accounts' => $accounts,
            'permissions' => [
                'manage' => Gate::allows('billing.manageSocialAccounts', $organization),
            ],
        ]);
    }

    public function connect(Request $request, Organization $organization, SocialProvider $provider): RedirectResponse
    {
        Gate::authorize('billing.manageSocialAccounts', $organization);

        $result = $this->manager->start($request->user(), $organization, $provider);

        if (($result['status'] ?? null) !== 'pending') {
            return redirect()
                ->route('organizations.connected-accounts.index', $organization)
                ->with('status', (string) ($result['message'] ?? 'Unable to start the social connection.'));
        }

        return redirect()->away((string) $result['authorization_url'], 302, [
            'X-Redirect' => '1',
        ]);
    }

    public function callback(Request $request, Organization $organization, SocialProvider $provider): RedirectResponse
    {
        Gate::authorize('billing.manageSocialAccounts', $organization);

        $result = $this->manager->complete(
            $request->user(),
            $organization,
            $provider,
            (string) $request->query('state'),
            $request->query('code') ? (string) $request->query('code') : null,
            (string) $request->query('redirect_uri', (string) config('services.social.redirect_uri', '')),
        );

        if (($result['status'] ?? null) !== 'pending') {
            return redirect()
                ->route('organizations.connected-accounts.index', $organization)
                ->with('status', (string) ($result['message'] ?? 'The social connection could not be completed.'));
        }

        return redirect()
            ->route('organizations.connected-accounts.index', $organization)
            ->with('status', 'The social connection was validated.');
    }

    public function destroy(Request $request, Organization $organization, ConnectedAccount $account): RedirectResponse
    {
        Gate::authorize('billing.manageSocialAccounts', $organization);

        abort_unless($account->organization_id === $organization->id, 404);

        $result = $this->manager->disconnect($request->user(), $organization, $account);

        if (($result['status'] ?? null) !== 'disconnected') {
            return redirect()
                ->route('organizations.connected-accounts.index', $organization)
                ->with('status', (string) ($result['message'] ?? 'The social connection could not be disconnected.'));
        }

        return redirect()
            ->route('organizations.connected-accounts.index', $organization)
            ->with('status', 'The social account was disconnected.');
    }
}
