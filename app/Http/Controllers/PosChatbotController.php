<?php

namespace App\Http\Controllers;

use App\Exceptions\PosChatbotUnavailable;
use App\Http\Requests\PosChatbotRequest;
use App\Services\PosChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosChatbotController extends Controller
{
    public function show(Request $request, PosChatbotService $chatbot): JsonResponse
    {
        return response()->json([
            'available' => $chatbot->available(),
            'messages' => $request->session()->get($this->sessionKey($request), []),
        ])->header('Cache-Control', 'no-store');
    }

    public function store(PosChatbotRequest $request, PosChatbotService $chatbot): JsonResponse
    {
        $history = $request->session()->get($this->sessionKey($request), []);

        try {
            $answer = $chatbot->answer(
                $request->validated('message'),
                $history,
                $request->validated('page'),
                $request->user()->isDemo(),
            );
        } catch (PosChatbotUnavailable) {
            return response()->json([
                'message' => 'Bantuan AI sedang tidak tersedia. Silakan coba lagi nanti.',
            ], 503);
        }

        $history[] = ['role' => 'user', 'content' => $request->validated('message')];
        $history[] = ['role' => 'assistant', ...$answer];
        $history = array_slice($history, -(int) config('chatbot.history_pairs') * 2);
        $request->session()->put($this->sessionKey($request), $history);

        return response()->json(['messages' => $history])->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->session()->forget($this->sessionKey($request));

        return response()->json(['messages' => []]);
    }

    private function sessionKey(Request $request): string
    {
        return 'pos_chatbot.'.$request->user()->getAuthIdentifier();
    }
}
