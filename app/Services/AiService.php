<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) (Setting::get('openai_api_key') ?? config('services.openai.key', env('OPENAI_API_KEY', '')));
        $this->model = (string) (Setting::get('openai_model') ?? config('services.openai.model', 'gpt-4o-mini'));
        $this->baseUrl = 'https://api.openai.com/v1';
    }

    /**
     * Generate an engaging, high-converting product description.
     */
    public function generateProductDescription(string $productName, ?string $category = null, array $features = []): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'OpenAI API key is not configured. Please add it in Admin Settings or .env file.',
            ];
        }

        $featureText = !empty($features) ? 'Key Highlights: ' . implode(', ', $features) : '';
        $prompt = "You are an expert e-commerce copywriter. Write a compelling, SEO-optimized product description for: '{$productName}'.
Category: {$category}
{$featureText}

Provide response in JSON format with two keys:
1. 'short_description': 2-3 sentences concise hook.
2. 'description': Detailed description with features, bullet points, and benefits in HTML format.";

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a helpful e-commerce copywriting assistant who outputs valid JSON.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.7,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = json_decode($data['choices'][0]['message']['content'] ?? '{}', true);

                return [
                    'success' => true,
                    'short_description' => $content['short_description'] ?? '',
                    'description' => $content['description'] ?? '',
                ];
            }

            Log::error('AI Service Error', ['response' => $response->body()]);
            return [
                'success' => false,
                'message' => 'Failed to generate description: ' . $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::error('AI Service Exception', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate SEO meta title, meta description, and keywords.
     */
    public function generateSeoMeta(string $productName, string $description): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'OpenAI API key is not configured.',
            ];
        }

        $prompt = "Analyze this product and generate optimized SEO metadata.
Product: {$productName}
Details: {$description}

Return JSON with:
1. 'meta_title' (max 60 characters)
2. 'meta_description' (max 155 characters)
3. 'keywords' (comma separated list of 5-8 relevant search terms)";

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(25)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are an SEO specialist who outputs strictly valid JSON.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.5,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = json_decode($data['choices'][0]['message']['content'] ?? '{}', true);

                return [
                    'success' => true,
                    'meta_title' => $content['meta_title'] ?? $productName,
                    'meta_description' => $content['meta_description'] ?? '',
                    'keywords' => $content['keywords'] ?? '',
                ];
            }

            return ['success' => false, 'message' => 'API response failed'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Customer support AI chatbot reply.
     */
    public function chatWithCustomer(string $userMessage, array $conversationHistory = []): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => true,
                'reply' => 'Hello! How may I assist you with your shopping today? (Note: AI service is currently in demo mode; please configure OpenAI API key for live smart responses).',
            ];
        }

        $storeName = Setting::get('store_name', config('app.name', 'Our Store'));
        $systemPrompt = "You are a friendly, helpful customer support assistant for {$storeName}, an e-commerce platform. Assist customers with inquiries regarding products, shipping policies, returns, order tracking, and recommendations. Keep answers helpful, courteous, and concise.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach (array_slice($conversationHistory, -6) as $msg) {
            $messages[] = [
                'role' => $msg['role'] ?? 'user',
                'content' => $msg['content'] ?? '',
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(20)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => 0.7,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $reply = $data['choices'][0]['message']['content'] ?? '';

                return [
                    'success' => true,
                    'reply' => $reply,
                ];
            }

            return [
                'success' => false,
                'reply' => 'I am experiencing a momentary connection glitch. Please reach out to our human support team.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'reply' => 'An error occurred while connecting to AI assistance. Please try again.',
            ];
        }
    }
}
