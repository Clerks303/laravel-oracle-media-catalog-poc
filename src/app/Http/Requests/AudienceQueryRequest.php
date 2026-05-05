<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared validation for /audience/* query strings. All fields nullable —
 * each endpoint reads back only the keys it actually consumes (perChannel
 * ignores limit/channel, topPrograms uses all four).
 */
class AudienceQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from'    => ['nullable', 'date'],
            'to'      => ['nullable', 'date', 'after_or_equal:from'],
            'limit'   => ['nullable', 'integer', 'min:1', 'max:100'],
            'channel' => ['nullable', 'string', 'max:16'],
        ];
    }
}
