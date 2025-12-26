<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatCompletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'model' => 'required|string',
            'messages' => 'required|array|min:1',
            'messages.*.role' => 'required|in:system,user,assistant',
            'messages.*.content' => 'required|string',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:1|max:128000',
            'stream' => 'nullable|boolean',
            'response_format' => 'nullable|array',
            'response_format.type' => 'nullable|in:text,json_object',
        ];
    }

    public function messages(): array
    {
        return [
            'model.required' => 'Model is required',
            'messages.required' => 'Messages array is required',
            'messages.*.role.required' => 'Each message must have a role',
            'messages.*.content.required' => 'Each message must have content',
        ];
    }
}

