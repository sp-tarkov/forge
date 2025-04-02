<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V0;

use App\Enums\Api\V0\ApiErrorCode;
use Exception;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

it('returns correct json for not found exception', function (): void {
    $response = $this->getJson('/api/v0/this-route-does-not-exist');

    $response->assertStatus(Response::HTTP_NOT_FOUND)
        ->assertExactJson([
            'success' => false,
            'code' => ApiErrorCode::NOT_FOUND->value,
            'message' => 'Resource not found.',
        ]);
});

it('returns correct json for validation exception', function (): void {
    Route::post('/api/v0/test-validation', function (): void {
        request()->validate(['name' => 'required']);
    });

    $response = $this->postJson('/api/v0/test-validation', []); // Empty data

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY) // 422
        ->assertJsonStructure([
            'success',
            'message',
            'errors' => [
                'name',
            ],
        ])
        ->assertJsonFragment([
            'success' => false,
            'message' => 'Validation failed.',
        ])
        ->assertJsonValidationErrorFor('name');
});

it('returns correct json for generic server error', function (): void {
    config(['app.debug' => false]); // Temporarily disable debug.

    // Define a route that throws a generic exception.
    Route::get('/api/v0/test-server-error', function (): void {
        throw new Exception('Something went really wrong!');
    });

    $response = $this->getJson('/api/v0/test-server-error');

    $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR) // 500
        ->assertExactJson([
            'success' => false,
            'code' => ApiErrorCode::SERVER_ERROR->value,
            'message' => 'An unexpected error occurred.', // Generic message
        ]);

    config(['app.debug' => true]); // Re-enable debug

    $responseDebug = $this->getJson('/api/v0/test-server-error');

    $responseDebug
        ->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR) // 500
        ->assertExactJson([
            'success' => false,
            'code' => ApiErrorCode::SERVER_ERROR->value,
            'message' => 'Something went really wrong!', // Detailed message
        ]);
});

it('does not return json for not found exceptions for non-api routes', function (): void {
    $response = $this->get('/this-route-does-not-exist');

    $response->assertStatus(Response::HTTP_NOT_FOUND);
    $response->assertHeader('content-type', 'text/html; charset=UTF-8'); // Should be HTML
    $response->assertDontSeeText('"success": false'); // Ensure not JSON
    $response->assertDontSeeText('Resource not found.');
});
