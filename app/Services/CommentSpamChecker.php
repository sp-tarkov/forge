<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Comment;
use App\Support\Akismet\SpamCheckResult;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Service for checking comments against Akismet spam detection.
 */
class CommentSpamChecker
{
    /**
     * The base URL for the Akismet API.
     */
    private const string BASE_URL = 'https://rest.akismet.com';

    /**
     * The number of seconds to wait before retrying an API request.
     */
    private const int TIMEOUT_SECONDS = 10;

    /**
     * The maximum number of times to retry an API request.
     */
    private const int MAX_RETRIES = 3;

    /**
     * Verify the Akismet API key is valid.
     */
    public function verifyKey(): bool
    {
        if (! config('akismet.enabled', false)) {
            return false;
        }

        $cacheKey = 'akismet:verify_key:'.config('akismet.api_key');

        return Cache::remember($cacheKey, now()->addDay(), function () {
            try {
                $response = $this->makeRequest('POST', '/1.1/verify-key', [
                    'key' => config('akismet.api_key'),
                    'blog' => config('akismet.blog_url'),
                ]);

                $isValid = $response->body() === 'valid';

                if (! $isValid) {
                    $debugHelp = $response->header('X-akismet-debug-help');
                    Log::warning('Akismet API key verification failed', [
                        'debug_help' => $debugHelp,
                    ]);
                }

                return $isValid;
            } catch (Exception $exception) {
                Log::error('Akismet key verification error', [
                    'error' => $exception->getMessage(),
                ]);

                return false;
            }
        });
    }

    /**
     * Check if a comment is spam using the Akismet API.
     */
    public function checkSpam(Comment $comment): SpamCheckResult
    {
        // If Akismet is disabled, return not spam but keep a note in the metadata.
        if (! config('akismet.enabled', false)) {
            return new SpamCheckResult(
                isSpam: false,
                metadata: ['reason' => 'akismet_disabled']
            );
        }

        // If the key is invalid, return not spam but keep a note in the metadata.
        if (! $this->verifyKey()) {
            return new SpamCheckResult(
                isSpam: false,
                metadata: ['error' => 'invalid_api_key']
            );
        }

        try {
            $akismetData = $this->prepareAkismetData($comment);

            $response = $this->makeRequest('POST', '/1.1/comment-check', $akismetData);

            $body = $response->body();
            $isSpam = $body === 'true';

            // Check for discard recommendation
            $proTip = $response->header('X-akismet-pro-tip');
            $discard = $proTip === 'discard';

            // Check for recheck after header
            $recheckAfter = $response->header('X-akismet-recheck-after');

            $metadata = [
                'akismet_response' => $body,
                'checked_at' => now()->toISOString(),
                'api_version' => '1.1',
            ];

            if ($proTip) {
                $metadata['pro_tip'] = $proTip;
            }

            if ($recheckAfter) {
                $metadata['recheck_after'] = $recheckAfter;
            }

            $result = new SpamCheckResult(
                isSpam: $isSpam,
                metadata: $metadata,
                discard: $discard,
                proTip: $proTip,
                recheckAfter: $recheckAfter
            );

            return $result;

        } catch (Throwable $throwable) {
            // Return safe fallback - assume not spam if API fails
            return new SpamCheckResult(
                isSpam: false,
                metadata: [
                    'error' => 'api_failure',
                    'error_message' => $throwable->getMessage(),
                ]
            );
        }
    }

    /**
     * Mark a comment as ham (not spam) in Akismet.
     */
    public function markAsHam(Comment $comment): void
    {
        if (! config('akismet.enabled', false)) {
            return;
        }

        if (! $this->verifyKey()) {
            return;
        }

        try {
            $akismetData = $this->prepareAkismetData($comment);

            $response = $this->makeRequest('POST', '/1.1/submit-ham', $akismetData);
        } catch (Throwable $throwable) {
            Log::error('Failed to mark comment as ham in Akismet', [
                'comment_id' => $comment->id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * Mark a comment as spam in Akismet.
     */
    public function markAsSpam(Comment $comment): void
    {
        if (! config('akismet.enabled', false)) {
            return;
        }

        if (! $this->verifyKey()) {
            return;
        }

        try {
            $akismetData = $this->prepareAkismetData($comment);

            $response = $this->makeRequest('POST', '/1.1/submit-spam', $akismetData);
        } catch (Throwable $throwable) {
            Log::error('Failed to mark comment as spam in Akismet', [
                'comment_id' => $comment->id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * Get usage statistics from Akismet.
     *
     * @return array<string, mixed>|null
     */
    public function getUsageLimit(): ?array
    {
        if (! config('akismet.enabled', false)) {
            return null;
        }

        try {
            $response = $this->makeRequest('GET', '/1.2/usage-limit', [
                'api_key' => config('akismet.api_key'),
            ]);

            $data = $response->json();

            return $data;
        } catch (Throwable $throwable) {
            Log::error('Failed to retrieve Akismet usage limit', [
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Prepare data for Akismet API call.
     *
     * @return array<string, mixed>
     */
    private function prepareAkismetData(Comment $comment): array
    {
        // Details on available values:
        // https://akismet.com/developers/detailed-docs/comment-check/
        $payload = [
            'api_key' => config('akismet.api_key'),
            'blog' => config('akismet.blog_url'),
            'user_ip' => $comment->user_ip,
            'user_agent' => $comment->user_agent,
            'referrer' => $comment->referrer,
            'permalink' => $comment->getUrl(),
            'comment_type' => $comment->isRoot() ? 'comment' : 'reply',
            'comment_author' => $comment->user->name,
            'comment_author_email' => $comment->user->email,
            'comment_content' => $comment->body,
            'comment_date_gmt' => $comment->created_at->utc()->toDateTimeString(),
            'comment_post_modified_gmt' => $comment->commentable->getAttribute('created_at')?->utc()->toDateTimeString(),
            'blog_lang' => config('app.locale', 'en'),
            'blog_charset' => 'UTF-8',
        ];

        if ($comment->user->isModOrAdmin()) {
            $payload['user_role'] = 'staff';
        }

        // Add test flag for non-production environments
        if (config('app.env', 'local') !== 'production') {
            $payload['is_test'] = '1';
        }

        return $payload;
    }

    /**
     * Make an HTTP request to the Akismet API with retry logic.
     *
     * @param  array<string, mixed>  $data
     */
    private function makeRequest(string $method, string $endpoint, array $data = []): Response
    {
        $client = Http::timeout(self::TIMEOUT_SECONDS)
            ->asForm()
            ->retry(self::MAX_RETRIES, 100, fn (Exception $exception): bool => $exception instanceof ConnectionException || ($exception instanceof RequestException && $exception->getCode() >= 500));

        $url = self::BASE_URL.$endpoint;

        try {
            if ($method === 'GET') {
                $response = $client->get($url, $data);
            } else {
                $response = $client->post($url, $data);
            }

            // Check for rate limiting
            throw_if($response->status() === 429, Exception::class, 'Akismet API rate limit exceeded');

            // Check for other HTTP errors
            throw_unless($response->successful(), Exception::class, 'Akismet API error: HTTP '.$response->status());
        } catch (Throwable $throwable) {
            Log::error('Akismet HTTP request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }

        return $response;
    }
}
