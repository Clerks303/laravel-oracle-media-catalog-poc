<?php

namespace App\Http\Requests;

use App\Models\Broadcast;
use App\Rules\BroadcastNonOverlapping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Backfill omitted fields from the existing broadcast so the
     * overlap check sees a complete (program_id, channel_id, scheduled_at) tuple
     * regardless of which field the client is patching.
     */
    protected function prepareForValidation(): void
    {
        /** @var Broadcast|null $b */
        $b = $this->route('broadcast');
        if (! $b) {
            return;
        }

        $this->merge([
            'program_id'   => $this->input('program_id',   $b->program_id),
            'channel_id'   => $this->input('channel_id',   $b->channel_id),
            'scheduled_at' => $this->input('scheduled_at', optional($b->scheduled_at)->toDateTimeString()),
        ]);
    }

    public function rules(): array
    {
        /** @var Broadcast|null $b */
        $b = $this->route('broadcast');

        $temporalChanged =
            ($b && (int) $this->input('program_id')   !== (int) $b->program_id)   ||
            ($b && (int) $this->input('channel_id')   !== (int) $b->channel_id)   ||
            ($b && (string) $this->input('scheduled_at') !== (string) optional($b->scheduled_at)->toDateTimeString());

        $scheduledRules = ['sometimes', 'date'];
        if ($temporalChanged) {
            $scheduledRules[] = new BroadcastNonOverlapping($b?->id);
        }

        return [
            'program_id'   => ['sometimes', 'integer', Rule::exists('programs', 'id')->whereNull('deleted_at')],
            'channel_id'   => ['sometimes', 'integer', 'exists:channels,id'],
            'scheduled_at' => $scheduledRules,
            'replay_until' => ['nullable', 'date', 'after:scheduled_at'],
        ];
    }
}
