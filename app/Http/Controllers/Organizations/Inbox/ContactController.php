<?php

namespace App\Http\Controllers\Organizations\Inbox;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inbox\StoreContactRequest;
use App\Http\Requests\Inbox\UpdateContactRequest;
use App\Models\Contact;
use App\Models\Organization;
use App\Services\Inbox\ContactService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function __construct(private readonly ContactService $contacts)
    {
    }

    public function index(Request $request, Organization $organization): Response
    {
        Gate::authorize('inbox.view', $organization);

        $contacts = $this->contacts->list($organization, (string) $request->query('search', ''));

        return Inertia::render('organizations/inbox/Contacts', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'contacts' => $contacts,
            'permissions' => [
                'manage' => Gate::allows('inbox.manage', $organization),
            ],
        ]);
    }

    public function store(StoreContactRequest $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('inbox.manage', $organization);

        $this->contacts->create($request->user(), $organization, $request->validated());

        return redirect()->route('organizations.inbox.contacts.index', [
            'organization' => $organization->slug,
        ]);
    }

    public function update(UpdateContactRequest $request, Organization $organization, Contact $contact): RedirectResponse
    {
        Gate::authorize('inbox.manage', $organization);

        $this->contacts->update($request->user(), $organization, $contact, $request->validated());

        return redirect()->route('organizations.inbox.contacts.index', [
            'organization' => $organization->slug,
        ]);
    }
}
