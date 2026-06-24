<?php

declare(strict_types=1);

use App\Models\License;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\User;

beforeEach(function (): void {
    // Disable honeypot for testing
    config()->set('honeypot.enabled', false);
});

describe('License selection', function (): void {
    it('saves license value when selected via the listbox', function (): void {
        $owner = User::factory()->withMfa()->create();
        $license = License::factory()->create(['name' => 'MIT License']);
        ModCategory::factory()->create(['title' => 'Test Category']);

        $this->actingAs($owner);

        $page = visit('/mod/create');

        $page->assertSee('Mod Information')
            ->assertNoJavascriptErrors()
            ->fill('name', 'Test Mod')
            ->fill('teaser', 'Test teaser text')
            ->fill('textarea[name="description"]', 'Full mod description here')
            ->click('internal:role=combobox[name="License"i]')
            ->waitForText('MIT License')
            ->click('MIT License')
            ->click('internal:role=combobox[name="Category"i]')
            ->waitForText('Test Category')
            ->click('Test Category')
            ->fill('input[placeholder*="github.com/username"]', 'https://github.com/test/test')
            ->click('button[type="submit"]')
            ->waitForText('Mod Created');

        $mod = Mod::query()->where('name', 'Test Mod')->first();
        expect($mod)->not->toBeNull();
        expect($mod->license_id)->toBe($license->id);
    });
});

describe('GUID normalization', function (): void {
    it('lowercases and strips invalid characters from GUID input', function (): void {
        $owner = User::factory()->withMfa()->create();
        License::factory()->create(['name' => 'MIT License']);
        ModCategory::factory()->create(['title' => 'Test Category']);

        $this->actingAs($owner);

        $page = visit('/mod/create');

        // "Com.Example.My_Mod!" should be normalized live to "com.example.mymod" (lowercased, underscore and
        // exclamation stripped).
        $page->assertSee('Mod Information')
            ->assertNoJavascriptErrors()
            ->fill('name', 'Test Mod')
            ->fill('teaser', 'Test teaser text')
            ->fill('textarea[name="description"]', 'Full mod description here')
            ->fill('input[placeholder="com.username.modname"]', 'Com.Example.My_Mod!')
            ->click('internal:role=combobox[name="License"i]')
            ->waitForText('MIT License')
            ->click('MIT License')
            ->click('internal:role=combobox[name="Category"i]')
            ->waitForText('Test Category')
            ->click('Test Category')
            ->fill('input[placeholder*="github.com/username"]', 'https://github.com/test/test')
            ->click('button[type="submit"]')
            ->waitForText('Mod Created');

        $mod = Mod::query()->where('name', 'Test Mod')->first();
        expect($mod)->not->toBeNull();
        expect($mod->guid)->toBe('com.example.mymod');
    });
});
