<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Translation\PotentiallyTranslatedString;
use Throwable;

class DirectDownloadLink implements ValidationRule
{
    /**
     * The detected content length from the validation.
     */
    public ?int $contentLength = null;

    /**
     * Run the validation rule to ensure the URL is a direct download link for a 7z or zip file.
     *
     * @param  Closure(string, ?string=):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || empty($value)) {
            $fail(__('The download link must be a valid URL.'));

            return;
        }

        // Basic URL validation
        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            $fail(__('The download link must be a valid URL.'));

            return;
        }

        try {
            // Make HEAD request with redirects and reasonable timeout
            $response = Http::timeout(30)
                ->withoutVerifying() // Some sites may have SSL issues
                ->head($value);

            if (! $response->successful()) {
                $fail(__('The download link is not accessible. Returned HTTP status code :status.', ['status' => $response->status()]));

                return;
            }

            // Get headers (case-insensitive)
            $headers = $response->headers();
            $contentType = mb_strtolower($response->header('content-type'));
            $contentDisposition = mb_strtolower($response->header('content-disposition'));
            $contentLength = $response->header('content-length');

            // Validate content-type starts with `application/`
            if (! str_starts_with($contentType, 'application/')) {
                $fail(__('This is not a direct download link. Please review our Content Guidelines.'));

                return;
            }

            // Check if the URL ends with .7z or .zip OR content-disposition has attachment with .7z or .zip filename
            $urlLowercase = mb_strtolower($value);
            $urlEndsWithSevenZip = str_ends_with($urlLowercase, '.7z');
            $urlEndsWithZip = str_ends_with($urlLowercase, '.zip');
            $hasValidDisposition = false;

            if ($contentDisposition) {
                $hasAttachment = str_contains($contentDisposition, 'attachment');
                $hasSevenZipFilename = str_contains($contentDisposition, '.7z');
                $hasZipFilename = str_contains($contentDisposition, '.zip');
                $hasValidDisposition = $hasAttachment && ($hasSevenZipFilename || $hasZipFilename);
            }

            if (! $urlEndsWithSevenZip && ! $urlEndsWithZip && ! $hasValidDisposition) {
                $fail(__('The download link must be for a 7-zip (.7z) or ZIP (.zip) file. Please review our Content Guidelines.'));

                return;
            }

            // Validate content-length is present and non-zero
            if (! $contentLength || ! is_numeric($contentLength) || (int) $contentLength <= 0) {
                $fail(__('The download link must provide a valid file size.'));

                return;
            }

            // Store the content length for potential use
            $this->contentLength = (int) $contentLength;

        } catch (ConnectionException) {
            $fail(__('Unable to connect to the download link. Please check the URL and try again.'));
        } catch (RequestException $e) {
            $fail(__('The download link request failed: :message', ['message' => $e->getMessage()]));
        } catch (Throwable) {
            $fail(__('An error occurred while validating the download link. Please try again.'));
        }
    }
}
