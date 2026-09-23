<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiChatService;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    public function chat(Request $request, AiChatService $aiChatService)
    {
        $validated = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:24'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:2000'],
        ]);

        $result = $aiChatService->respond($validated['messages']);

        return response()->json([
            'success' => true,
            'data' => $result,
        ], 200);
    }
}
