<?php

declare(strict_types=1);

use App\Models\License;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

describe('Cheat Notice', function (): void {

    describe('Mod Show Page', function (): void {
        beforeEach(function (): void {
            $this->sptVersion = SptVersion::factory()->state(['version' => '3.8.0'])->create();
        });

        it('displays warning when cheat notice is enabled', function (): void {
            $mod = Mod::factory()
                ->withCheatNotice()
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0', 'published_at' => now()->subDay()])
                ->create(['published_at' => now()->subDay()]);

            // Verify the flag is set
            expect($mod->cheat_notice)->toBeTrue();

            $response = $this->get(route('mod.show', [$mod->id, $mod->slug]));

            $response->assertStatus(200);

            // Check for the red warning box with cheat notice
            $response->assertSee('bg-red-600');
            $response->assertSee('similar to traditional multiplayer');
            $response->assertSee('will not work and will result in an immediate and permanent ban');
        });

        it('does not display warning when cheat notice is disabled', function (): void {
            $mod = Mod::factory()
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0', 'published_at' => now()->subDay()])
                ->create(['cheat_notice' => false, 'published_at' => now()->subDay()]);

            $response = $this->get(route('mod.show', [$mod->id, $mod->slug]));

            $response->assertStatus(200)
                ->assertDontSee('will not work and will result in an immediate and permanent ban');
        });

        it('does not display warning by default', function (): void {
            $mod = Mod::factory()
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0', 'published_at' => now()->subDay()])
                ->create(['published_at' => now()->subDay()]);

            // Default should be falsy (false or null)
            expect($mod->cheat_notice)->toBeFalsy();

            $response = $this->get(route('mod.show', [$mod->id, $mod->slug]));

            $response->assertStatus(200)
                ->assertDontSee('will not work and will result in an immediate and permanent ban');
        });
    });

    describe('Create Form', function (): void {
        beforeEach(function (): void {
            config()->set('honeypot.enabled', false);
            $this->user = User::factory()->withMfa()->create();
            $this->license = License::factory()->create();
            $this->category = ModCategory::factory()->create();
        });

        it('allows enabling the cheat notice', function (): void {
            $this->actingAs($this->user);

            Livewire::test('pages::mod.create')
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('name', 'Test Cheat Mod')
                ->set('guid', 'com.test.cheatlike')
                ->set('teaser', 'Test teaser')
                ->set('description', 'Test description')
                ->set('license', (string) $this->license->id)
                ->set('category', (string) $this->category->id)
                ->set('sourceCodeLinks.0.url', 'https://github.com/test/repo')
                ->set('sourceCodeLinks.0.label', '')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->set('cheatNotice', true)
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();

            $mod = Mod::query()->where('name', 'Test Cheat Mod')->first();
            expect($mod)->not->toBeNull();
            expect($mod->cheat_notice)->toBeTrue();
        });

        it('defaults to disabled', function (): void {
            $this->actingAs($this->user);

            $component = Livewire::test('pages::mod.create');

            expect($component->instance()->cheatNotice)->toBeFalse();
        });
    });

    describe('Edit Form', function (): void {
        beforeEach(function (): void {
            config()->set('honeypot.enabled', false);
            SptVersion::factory()->state(['version' => '3.8.0'])->create();
            $this->user = User::factory()->withMfa()->create();
            $this->license = License::factory()->create();
            $this->category = ModCategory::factory()->create();
        });

        it('loads existing cheat notice setting', function (): void {
            $mod = Mod::factory()
                ->for($this->user, 'owner')
                ->for($this->license)
                ->for($this->category, 'category')
                ->withCheatNotice()
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
                ->create();

            $this->actingAs($this->user);

            $component = Livewire::test('pages::mod.edit', ['modId' => $mod->id, 'slug' => $mod->slug]);

            expect($component->instance()->cheatNotice)->toBeTrue();
        });

        it('allows updating the setting', function (): void {
            $mod = Mod::factory()
                ->for($this->user, 'owner')
                ->for($this->license)
                ->for($this->category, 'category')
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
                ->create(['cheat_notice' => false]);

            $this->actingAs($this->user);

            Livewire::test('pages::mod.edit', ['modId' => $mod->id, 'slug' => $mod->slug])
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('cheatNotice', true)
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();

            $mod->refresh();
            expect($mod->cheat_notice)->toBeTrue();
        });
    });

    describe('API', function (): void {
        beforeEach(function (): void {
            Cache::clear();

            $this->user = User::factory()->create([
                'password' => Hash::make('password'),
            ]);

            $this->token = $this->user->createToken('test-token')->plainTextToken;

            SptVersion::factory()->state(['version' => '3.8.0'])->create();
        });

        it('returns cheat_notice in response', function (): void {
            $mod = Mod::factory()
                ->withCheatNotice()
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
                ->create();

            $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d?fields=cheat_notice', $mod->id));

            $response->assertOk()
                ->assertJsonPath('data.cheat_notice', true);
        });

        it('returns false by default', function (): void {
            $mod = Mod::factory()
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
                ->create();

            $response = $this->withToken($this->token)->getJson(sprintf('/api/v0/mod/%d?fields=cheat_notice', $mod->id));

            $response->assertOk()
                ->assertJsonPath('data.cheat_notice', false);
        });

        it('filters by cheat_notice true', function (): void {
            $modWithNotice = Mod::factory()
                ->withCheatNotice()
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
                ->create();

            $modWithoutNotice = Mod::factory()
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
                ->create(['cheat_notice' => false]);

            $response = $this->withToken($this->token)->getJson('/api/v0/mods?filter[cheat_notice]=true');

            $response->assertOk();
            $modIds = collect($response->json('data'))->pluck('id')->all();
            expect($modIds)->toContain($modWithNotice->id);
            expect($modIds)->not->toContain($modWithoutNotice->id);
        });

        it('filters by cheat_notice false', function (): void {
            $modWithNotice = Mod::factory()
                ->withCheatNotice()
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
                ->create();

            $modWithoutNotice = Mod::factory()
                ->hasVersions(1, ['spt_version_constraint' => '3.8.0'])
                ->create(['cheat_notice' => false]);

            $response = $this->withToken($this->token)->getJson('/api/v0/mods?filter[cheat_notice]=false');

            $response->assertOk();
            $modIds = collect($response->json('data'))->pluck('id')->all();
            expect($modIds)->toContain($modWithoutNotice->id);
            expect($modIds)->not->toContain($modWithNotice->id);
        });
    });

    describe('Factory', function (): void {
        it('creates mod with cheat notice using state method', function (): void {
            $mod = Mod::factory()->withCheatNotice()->create();

            expect($mod->cheat_notice)->toBeTrue();
        });

        it('creates mod without cheat notice by default', function (): void {
            $mod = Mod::factory()->create();

            // Default should be falsy (false or null)
            expect($mod->cheat_notice)->toBeFalsy();
        });
    });
});
