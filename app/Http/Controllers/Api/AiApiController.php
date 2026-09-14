<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiApiController extends Controller
{
    protected AiService $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * AI copy generator for admin product management.
     */
    public function generateDescription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'features' => 'nullable|array',
        ]);

        $result = $this->aiService->generateProductDescription(
            $validated['name'],
            $validated['category'] ?? null,
            $validated['features'] ?? []
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * AI SEO metadata generator.
     */
    public function generateSeo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        $result = $this->aiService->generateSeoMeta(
            $validated['name'],
            $validated['description']
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Customer support AI chatbot endpoint.
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'nullable|array',
        ]);

        $result = $this->aiService->chatWithCustomer(
            $validated['message'],
            $validated['history'] ?? []
        );

        return response()->json($result);
    }
}
