<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

/**
 * Example of a business endpoint protected by the central entitlement
 * middleware. Real modules replace this with their own controllers; the
 * pattern (route middleware, never table inspection) stays the same.
 */
class FeatureController extends Controller
{
    public function auditLog(Organization $organization): JsonResponse
    {
        return response()->json([
            'organization' => $organization->slug,
            'feature' => 'audit_log',
            'available' => true,
        ]);
    }
}
