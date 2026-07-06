<?php

declare(strict_types=1);

use Anthropic\Client;
use App\Services\ClaudeCommentTranslationService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Build an Anthropic API message payload whose single text block contains the given content.
 *
 * @return array<string, mixed>
 */
function fakeClaudeTranslationMessage(string $text, string $stopReason = 'end_turn'): array
{
    return [
        'id' => 'msg_test',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-haiku-4-5',
        'content' => $text === '' ? [] : [['type' => 'text', 'text' => $text]],
        'stop_reason' => $stopReason,
        'stop_sequence' => null,
        'usage' => ['input_tokens' => 100, 'output_tokens' => 42],
    ];
}

beforeEach(function (): void {
    $this->mockHandler = new MockHandler;

    $client = new Client(apiKey: 'test-key', requestOptions: [
        'transporter' => new GuzzleClient(['handler' => HandlerStack::create($this->mockHandler)]),
    ]);

    $this->service = new ClaudeCommentTranslationService($client);
});

describe('Single Translation', function (): void {
    test('parses a translation for a non-English comment', function (): void {
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(
            fakeClaudeTranslationMessage((string) json_encode([
                'detected_language' => 'ru',
                'is_english' => false,
                'translation' => 'Hello, great mod!',
            ]))
        )));

        $result = $this->service->translate('Привет, отличный мод!');

        expect($result->detectedLanguage)->toBe('ru')
            ->and($result->translatedBody)->toBe('Hello, great mod!')
            ->and($result->isError())->toBeFalse()
            ->and($result->metadata)->toHaveKey('provider', 'anthropic')
            ->and($result->metadata)->toHaveKey('input_tokens', 100)
            ->and($result->metadata)->toHaveKey('output_tokens', 42);
    });

    test('returns no translation when the model confirms the comment is English', function (): void {
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(
            fakeClaudeTranslationMessage((string) json_encode([
                'detected_language' => 'en',
                'is_english' => true,
                'translation' => 'This should be discarded.',
            ]))
        )));

        $result = $this->service->translate('This mod is great, thanks!');

        expect($result->detectedLanguage)->toBe('en')
            ->and($result->translatedBody)->toBeNull()
            ->and($result->isError())->toBeFalse();
    });

    test('returns an error result when the model refuses', function (): void {
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(
            fakeClaudeTranslationMessage('', 'refusal')
        )));

        $result = $this->service->translate('Some comment body.');

        expect($result->isError())->toBeTrue()
            ->and($result->metadata)->toHaveKey('error', 'refusal');
    });

    test('returns an error result when the response is not valid JSON', function (): void {
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(
            fakeClaudeTranslationMessage('not json at all')
        )));

        $result = $this->service->translate('Some comment body.');

        expect($result->isError())->toBeTrue()
            ->and($result->metadata)->toHaveKey('error', 'invalid_response');
    });
});

describe('Batch Translation', function (): void {
    test('submits nothing for an empty body list', function (): void {
        expect($this->service->submitBatch([]))->toBeNull()
            ->and($this->mockHandler->getLastRequest())->toBeNull();
    });

    test('submits a batch and returns the provider batch ID', function (): void {
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
            'id' => 'msgbatch_test',
            'type' => 'message_batch',
            'processing_status' => 'in_progress',
            'request_counts' => ['processing' => 2, 'succeeded' => 0, 'errored' => 0, 'canceled' => 0, 'expired' => 0],
            'created_at' => '2026-07-05T00:00:00Z',
            'expires_at' => '2026-07-06T00:00:00Z',
            'archived_at' => null,
            'cancel_initiated_at' => null,
            'ended_at' => null,
            'results_url' => null,
        ])));

        $batchId = $this->service->submitBatch([42 => 'Привет, отличный мод!', 43 => 'Danke für den tollen Mod!']);

        expect($batchId)->toBe('msgbatch_test');

        $payload = json_decode((string) $this->mockHandler->getLastRequest()?->getBody(), true);
        expect($payload['requests'])->toHaveCount(2)
            ->and($payload['requests'][0]['custom_id'])->toBe('42')
            ->and($payload['requests'][0]['params']['model'])->toBe('claude-haiku-4-5')
            ->and($payload['requests'][0]['params']['max_tokens'])->toBe(8192)
            ->and($payload['requests'][0]['params']['output_config']['format']['type'])->toBe('json_schema');
    });

    test('reports whether a batch is complete', function (): void {
        $batchPayload = [
            'id' => 'msgbatch_test',
            'type' => 'message_batch',
            'processing_status' => 'in_progress',
            'request_counts' => ['processing' => 1, 'succeeded' => 0, 'errored' => 0, 'canceled' => 0, 'expired' => 0],
            'created_at' => '2026-07-05T00:00:00Z',
            'expires_at' => '2026-07-06T00:00:00Z',
            'archived_at' => null,
            'cancel_initiated_at' => null,
            'ended_at' => null,
            'results_url' => null,
        ];

        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($batchPayload)));
        expect($this->service->isBatchComplete('msgbatch_test'))->toBeFalse();

        $batchPayload['processing_status'] = 'ended';
        $batchPayload['ended_at'] = '2026-07-05T01:00:00Z';
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($batchPayload)));
        expect($this->service->isBatchComplete('msgbatch_test'))->toBeTrue();
    });

    test('parses successful and failed batch results', function (): void {
        $lines = implode("\n", [
            (string) json_encode([
                'custom_id' => '42',
                'result' => [
                    'type' => 'succeeded',
                    'message' => fakeClaudeTranslationMessage((string) json_encode([
                        'detected_language' => 'ru',
                        'is_english' => false,
                        'translation' => 'Hello, great mod!',
                    ])),
                ],
            ]),
            (string) json_encode([
                'custom_id' => '43',
                'result' => [
                    'type' => 'errored',
                    'error' => [
                        'type' => 'error',
                        'request_id' => 'req_test',
                        'error' => ['type' => 'api_error', 'message' => 'Internal server error'],
                    ],
                ],
            ]),
        ]);

        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/x-jsonl'], $lines));

        $results = $this->service->getBatchResults('msgbatch_test');

        expect($results)->toHaveKeys(['42', '43'])
            ->and($results['42']->detectedLanguage)->toBe('ru')
            ->and($results['42']->translatedBody)->toBe('Hello, great mod!')
            ->and($results['42']->isError())->toBeFalse()
            ->and($results['43']->isError())->toBeTrue()
            ->and($results['43']->metadata)->toHaveKey('error', 'batch_errored');
    });
});
