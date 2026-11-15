<?php

namespace App\Http\Controllers;

use App\Services\Communication\Webhooks\WebhookManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public provider callback endpoint.
 *
 * No browser session is required. Every request is verified by the adapter
 * (signature, secret token or challenge) before anything is persisted.
 */
class WebhookController extends Controller
{
    public function __construct(private readonly WebhookManager $webhooks)
    {
    }

    public function handle(Request $request, string $provider): JsonResponse|Response
    {
        $headers = [];

        foreach ($request->headers->all() as $key => $values) {
            $headers[strtolower((string) $key)] = (string) ($values[0] ?? '');
        }

        $payload = $request->isMethod('get')
            ? $request->query->all()
            : $request->all();

        $result = $this->webhooks->handle(
            $provider,
            $request->getMethod(),
            $payload,
            $headers,
            $request->getContent(),
            $request->query->all(),
        );

        if (is_string($result['body'])) {
            return response($result['body'], $result['status'])
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response()->json($result['body'], $result['status']);
    }
}
