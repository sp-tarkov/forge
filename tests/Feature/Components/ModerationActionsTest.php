<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Models\Mod;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->adminUser = User::factory()->admin()->create();
    $this->modUser = User::factory()->moderator()->create();
    $this->regularUser = User::factory()->create();
});

it('requires moderator or admin access', function (): void {
    $this->actingAs($this->regularUser);

    Livewire::test('pages::admin.moderation-actions')
        ->assertStatus(403);
});

it('allows moderator access', function (): void {
    $this->actingAs($this->modUser);

    Livewire::test('pages::admin.moderation-actions')
        ->assertStatus(200);
});

it('allows admin access', function (): void {
    $this->actingAs($this->adminUser);

    Livewire::test('pages::admin.moderation-actions')
        ->assertStatus(200);
});

it('displays moderation actions', function (): void {
    $mod = Mod::factory()->create(['name' => 'Test Mod']);

    TrackingEvent::factory()->moderationAction()->create([
        'event_name' => TrackingEventType::MOD_DISABLE->value,
        'visitor_id' => $this->adminUser->id,
        'visitable_type' => Mod::class,
        'visitable_id' => $mod->id,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test('pages::admin.moderation-actions')
        ->assertSee('Disabled mod');
});

it('can filter by event type', function (): void {
    $mod1 = Mod::factory()->create();
    $mod2 = Mod::factory()->create();

    TrackingEvent::factory()->moderationAction()->create([
        'event_name' => TrackingEventType::MOD_DISABLE->value,
        'visitor_id' => $this->adminUser->id,
        'visitable_type' => Mod::class,
        'visitable_id' => $mod1->id,
    ]);

    TrackingEvent::factory()->moderationAction()->create([
        'event_name' => TrackingEventType::MOD_FEATURE->value,
        'visitor_id' => $this->adminUser->id,
        'visitable_type' => Mod::class,
        'visitable_id' => $mod2->id,
    ]);

    $this->actingAs($this->adminUser);

    // Without filter, should have 2 actions
    $component = Livewire::test('pages::admin.moderation-actions');
    expect($component->instance()->actions->total())->toBe(2);

    // With filter, should have 1 action
    $component->set('eventTypeFilter', TrackingEventType::MOD_DISABLE->value);
    expect($component->instance()->actions->total())->toBe(1);
    $component->assertSee('Disabled mod');
});

it('can filter by moderator', function (): void {
    $this->adminUser->update(['name' => 'Admin User']);
    $this->modUser->update(['name' => 'Moderator User']);

    $mod1 = Mod::factory()->create();
    $mod2 = Mod::factory()->create();

    TrackingEvent::factory()->moderationAction()->create([
        'event_name' => TrackingEventType::MOD_DISABLE->value,
        'visitor_id' => $this->adminUser->id,
        'visitable_type' => Mod::class,
        'visitable_id' => $mod1->id,
    ]);

    TrackingEvent::factory()->moderationAction()->create([
        'event_name' => TrackingEventType::MOD_DISABLE->value,
        'visitor_id' => $this->modUser->id,
        'visitable_type' => Mod::class,
        'visitable_id' => $mod2->id,
    ]);

    $this->actingAs($this->adminUser);

    // Without filter, should have 2 actions
    $component = Livewire::test('pages::admin.moderation-actions');
    expect($component->instance()->actions->total())->toBe(2);

    // With filter, should have 1 action from admin user
    $component->set('moderatorFilter', (string) $this->adminUser->id);
    expect($component->instance()->actions->total())->toBe(1);

    // Verify the filtered action is from the admin user
    $filteredAction = $component->instance()->actions->first();
    expect($filteredAction->visitor_id)->toBe($this->adminUser->id);
});

it('can reset filters', function (): void {
    $this->actingAs($this->adminUser);

    Livewire::test('pages::admin.moderation-actions')
        ->set('eventTypeFilter', TrackingEventType::MOD_DISABLE->value)
        ->set('moderatorFilter', (string) $this->adminUser->id)
        ->set('reportLinkedOnly', true)
        ->call('resetFilters')
        ->assertSet('eventTypeFilter', '')
        ->assertSet('moderatorFilter', '')
        ->assertSet('reportLinkedOnly', false);
});

it('displays moderator name on actions', function (): void {
    $this->adminUser->update(['name' => 'Admin Moderator']);
    $mod = Mod::factory()->create();

    TrackingEvent::factory()->moderationAction()->create([
        'event_name' => TrackingEventType::MOD_DISABLE->value,
        'visitor_id' => $this->adminUser->id,
        'visitable_type' => Mod::class,
        'visitable_id' => $mod->id,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test('pages::admin.moderation-actions')
        ->assertSee('Admin Moderator');
});

it('displays active filters', function (): void {
    $this->actingAs($this->adminUser);

    $component = Livewire::test('pages::admin.moderation-actions')
        ->set('eventTypeFilter', TrackingEventType::MOD_DISABLE->value)
        ->set('reportLinkedOnly', true);

    $activeFilters = $component->instance()->getActiveFilters();

    expect($activeFilters)->toContain('Type: Disabled mod');
    expect($activeFilters)->toContain('Linked to reports only');
});

it('shows empty state when no actions exist', function (): void {
    $this->actingAs($this->adminUser);

    Livewire::test('pages::admin.moderation-actions')
        ->assertSee('No moderation actions found');
});

it('paginates actions correctly', function (): void {
    $mods = Mod::factory()->count(30)->create();

    foreach ($mods as $mod) {
        TrackingEvent::factory()->moderationAction()->create([
            'event_name' => TrackingEventType::MOD_DISABLE->value,
            'visitor_id' => $this->adminUser->id,
            'visitable_type' => Mod::class,
            'visitable_id' => $mod->id,
        ]);
    }

    $this->actingAs($this->adminUser);

    $component = Livewire::test('pages::admin.moderation-actions');

    expect($component->instance()->actions->count())->toBeLessThanOrEqual(25);
    expect($component->instance()->actions->total())->toBe(30);
});
