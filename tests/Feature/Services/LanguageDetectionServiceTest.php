<?php

declare(strict_types=1);

use App\Services\LanguageDetectionService;

beforeEach(function (): void {
    $this->service = resolve(LanguageDetectionService::class);
});

describe('Language Detection', function (): void {
    test('detects English text reliably', function (): void {
        $result = $this->service->detect('Hello there, this is a great mod and I love using it every day! Thank you for your work.');

        expect($result->language)->toBe('en')
            ->and($result->reliable)->toBeTrue()
            ->and($result->tooShort)->toBeFalse()
            ->and($result->isConfidentEnglish())->toBeTrue();
    });

    test('detects Russian text as non-English', function (): void {
        $result = $this->service->detect('Привет, отличный мод! Спасибо за вашу работу, продолжай в том же духе.');

        expect($result->language)->toBe('ru')
            ->and($result->isConfidentEnglish())->toBeFalse();
    });

    test('flags short Latin-script text as too short', function (): void {
        $result = $this->service->detect('gg nice');

        expect($result->tooShort)->toBeTrue()
            ->and($result->language)->toBeNull()
            ->and($result->isConfidentEnglish())->toBeFalse();
    });

    test('does not flag short non-Latin text as too short', function (): void {
        $result = $this->service->detect('Спасибо большое!');

        expect($result->tooShort)->toBeFalse()
            ->and($result->isConfidentEnglish())->toBeFalse();
    });

    test('ignores code blocks when detecting the language', function (): void {
        $result = $this->service->detect("```php\n\$переменная = получить_значение();\n```\nWorks great now, thank you so much for the quick fix!");

        expect($result->strippedText)->toBe('Works great now, thank you so much for the quick fix!')
            ->and($result->language)->toBe('en');
    });

    test('ignores inline code, links, and mentions when detecting the language', function (): void {
        $result = $this->service->detect('Hey @someUser, the `config.json` file from [this guide](https://example.com/guide) fixed everything for me, thanks a lot!');

        expect($result->strippedText)->not->toContain('config.json')
            ->and($result->strippedText)->not->toContain('example.com')
            ->and($result->strippedText)->not->toContain('someUser')
            ->and($result->strippedText)->toContain('this guide')
            ->and($result->language)->toBe('en');
    });

    test('treats an empty comment as too short', function (): void {
        $result = $this->service->detect('');

        expect($result->tooShort)->toBeTrue()
            ->and($result->language)->toBeNull();
    });

    test('treats a comment containing only code as too short', function (): void {
        $result = $this->service->detect("```\nerror code 123\n```");

        expect($result->tooShort)->toBeTrue();
    });
});
