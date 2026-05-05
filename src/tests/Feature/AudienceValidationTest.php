<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Validation lives in AudienceQueryRequest, shared by both endpoints.
 * These tests run on any driver — they exercise the FormRequest, not the
 * PL/SQL package.
 */

it('rejects a malformed from on per-channel', function () {
    $this->getJson('/api/v1/audience/per-channel?from=not-a-date')
        ->assertStatus(422)
        ->assertJsonValidationErrors('from');
});

it('rejects to before from on per-channel', function () {
    $this->getJson('/api/v1/audience/per-channel?from=2026-12-01&to=2026-01-01')
        ->assertStatus(422)
        ->assertJsonValidationErrors('to');
});

it('rejects an oversized limit on top-programs', function () {
    $this->getJson('/api/v1/audience/top-programs?limit=999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('limit');
});

it('rejects a too-long channel code on top-programs', function () {
    $this->getJson('/api/v1/audience/top-programs?channel=' . str_repeat('A', 17))
        ->assertStatus(422)
        ->assertJsonValidationErrors('channel');
});
