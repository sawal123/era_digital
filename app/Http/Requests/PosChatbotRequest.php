<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PosChatbotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:'.config('chatbot.max_message_length')],
            'page' => ['nullable', 'string', 'max:150'],
            'history' => ['prohibited'],
            'messages' => ['prohibited'],
            'role' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Tulis pertanyaan tentang aplikasi POS terlebih dahulu.',
            'message.max' => 'Pertanyaan maksimal :max karakter.',
        ];
    }
}
