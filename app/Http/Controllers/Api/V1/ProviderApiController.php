<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CredentialingCase;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderApiController extends Controller
{
    public function cases(Request $request): JsonResponse
    {
        $provider = $request->user()->providerDetails;

        $cases = CredentialingCase::where('provider_id', $provider->id)
            ->with(['payer:id,name', 'status:id,name,dashboard_category'])
            ->latest()
            ->get()
            ->map(fn ($case) => [
                'case_number' => $case->case_number,
                'payer' => $case->payer->name ?? null,
                'status' => $case->status->name ?? null,
                'status_category' => $case->status->dashboard_category ?? null,
                'checklist' => $case->checklist_completion,
                'intake_date' => $case->intake_date?->toDateString(),
                'effective_date' => $case->effective_date?->toDateString(),
            ]);

        return response()->json(['data' => $cases]);
    }

    public function documents(Request $request): JsonResponse
    {
        $provider = $request->user()->providerDetails;

        $documents = Document::where('provider_id', $provider->id)
            ->with('documentType:id,name')
            ->latest()
            ->get()
            ->map(fn ($doc) => [
                'title' => $doc->title,
                'type' => $doc->documentType->name ?? null,
                'expiry_date' => $doc->expiry_date?->toDateString(),
                'status' => $doc->status,
            ]);

        return response()->json(['data' => $documents]);
    }

    public function createToken(Request $request): JsonResponse
    {
        $request->validate(['device_name' => 'required|string|max:255']);

        $token = $request->user()->createToken($request->device_name);

        return response()->json(['token' => $token->plainTextToken]);
    }
}
