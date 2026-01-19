<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Ribbon Blade Component', function (): void {
    it('renders ribbon with color and label', function (): void {
        $view = $this->blade('<x-ribbon color="red" label="Test Label" />');

        $view->assertSee('ribbon red z-10');
        $view->assertSee('Test Label');
    });

    it('renders different colors correctly', function (): void {
        $colors = ['red', 'amber', 'emerald', 'sky', 'yellow'];

        foreach ($colors as $color) {
            $view = $this->blade('<x-ribbon :color="$color" label="Test" />', ['color' => $color]);
            $view->assertSee(sprintf('ribbon %s z-10', $color));
        }
    });

    it('escapes label content for security', function (): void {
        $maliciousLabel = '<script>alert("xss")</script>';

        $view = $this->blade('<x-ribbon color="red" :label="$label" />', ['label' => $maliciousLabel]);

        $view->assertDontSee('<script>alert("xss")</script>', false);
        $view->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
    });

    it('does not render when color is missing', function (): void {
        $view = $this->blade('<x-ribbon label="Test Label" />');

        $view->assertDontSee('class="ribbon');
        $view->assertDontSee('Test Label');
    });

    it('does not render when label is missing', function (): void {
        $view = $this->blade('<x-ribbon color="red" />');

        $view->assertDontSee('class="ribbon');
    });

    it('does not render when both color and label are missing', function (): void {
        $view = $this->blade('<x-ribbon />');

        $view->assertDontSee('class="ribbon');
    });

    it('handles empty string values correctly', function (): void {
        $view = $this->blade('<x-ribbon color="" label="" />');

        $view->assertDontSee('class="ribbon');
    });

    it('handles null values correctly', function (): void {
        $view = $this->blade('<x-ribbon :color="null" :label="null" />');

        $view->assertDontSee('class="ribbon');
    });

    it('renders with dynamic content', function (): void {
        $color = 'emerald';
        $label = 'Dynamic Label';

        $view = $this->blade('<x-ribbon :color="$color" :label="$label" />', [
            'color' => $color,
            'label' => $label,
        ]);

        $view->assertSee(sprintf('ribbon %s z-10', $color));
        $view->assertSee($label);
    });
});
