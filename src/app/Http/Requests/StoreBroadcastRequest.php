<?php

namespace App\Http\Requests;

use App\Rules\BroadcastNonOverlapping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'program_id'   => ['required', 'integer', Rule::exists('programs', 'id')->whereNull('deleted_at')],
            'channel_id'   => ['required', 'integer', 'exists:channels,id'],
            'scheduled_at' => ['required', 'date', new BroadcastNonOverlapping()],
            'replay_until' => ['nullable', 'date', 'after:scheduled_at'],
        ];
    }
}
