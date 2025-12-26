<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\LlmException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChatCompletionRequest;
use App\Models\Project;
use App\Services\Llm\GatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatCompletionController extends Controller
{
    public function __construct(private GatewayService $gatewayService) {}

    public function create(ChatCompletionRequest $request): JsonResponse
    {
        $project = $request->get('project');
        $apiKey = $request->get('api_key');
        $requestId = $request->header('x-request-id');

        try {
            $response = $this->gatewayService->processRequest(
                payload: $request->validated(),
                project: $project,
                userId: auth()->id(),
                apiKeyId: $apiKey?->id,
                headers: [
                    'x-request-id' => $requestId,
                    'x-quality' => $request->header('x-quality'),
                ],
            );

            return response()->json($response);
        } catch (LlmException $e) {
            return response()->json([
                'error' => [
                    'type' => $e->errorType,
                    'message' => $e->getMessage(),
                    'request_id' => $requestId,
                    'details' => $e->details,
                ],
            ], $e->statusCode ?? 500);
        }
    }
}

