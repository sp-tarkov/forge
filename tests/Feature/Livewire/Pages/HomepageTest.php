<?php

declare(strict_types=1);

use App\Enums\SpamStatus;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

afterEach(function (): void {
    Cache::clear();
});

it('renders the homepage', function (): void {
    $this->get('/')->assertOk();
});

describe('homepage featured mods', function (): void {
    it('should only display featured mods in the featured section', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        Mod::factory()->count(3)->create(['featured' => true]);
        Mod::factory()->count(3)->create(['featured' => false]);
        Mod::all()->each(function ($mod): void {
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
        });

        // Assert that the featured mods are the ones that are actually featured
        $featured = Livewire::test('pages::homepage')
            ->assertViewHas('featured', fn ($featured) => $featured->every(fn ($mod) => $mod->featured));
    });
});

describe('homepage mod visibility', function (): void {
    it('should not display disabled mods', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        Mod::factory()->count(3)->create(['featured' => true]);
        Mod::factory()->count(3)->disabled()->create(['featured' => false]);
        Mod::all()->each(function ($mod): void {
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
        });

        $homepage = Livewire::test('pages::homepage')
            ->assertViewHas('featured', fn (Collection $featured) => $featured->every(fn (Mod $mod) => $mod->featured))
            ->assertViewHas('featured', fn (Collection $featured): bool => $featured->count() === 3)
            ->assertViewHas('newest', fn (Collection $latest) => $latest->every(fn (Mod $mod): bool => ! $mod->disabled))
            ->assertViewHas('newest', fn (Collection $latest): bool => $latest->count() === 3)
            ->assertViewHas('updated', fn (Collection $updated) => $updated->every(fn (Mod $mod): bool => ! $mod->disabled))
            ->assertViewHas('updated', fn (Collection $updated): bool => $updated->count() === 3);
    });

    it('should not display mods with no mod versions', function (): void {
        Mod::factory()->count(3)->create(['featured' => true]);

        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create(['featured' => true]);
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $homepage = Livewire::test('pages::homepage')
            ->assertViewHas('featured', fn (Collection $featured): bool => $featured->count() === 1)
            ->assertViewHas('newest', fn (Collection $featured): bool => $featured->count() === 1)
            ->assertViewHas('updated', fn (Collection $featured): bool => $featured->count() === 1);
    });

    it('should display disabled mods for administrators', function (): void {
        $this->actingAs(User::factory()->admin()->create());

        SptVersion::factory()->create(['version' => '1.0.0']);
        Mod::factory()->create(['featured' => true]);
        Mod::factory()->create(['featured' => true, 'disabled' => true]);
        Mod::factory()->create(['featured' => false]);
        Mod::factory()->create(['featured' => false, 'disabled' => true]);
        Mod::all()->each(function ($mod): void {
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
        });

        $homepage = Livewire::test('pages::homepage')
            ->assertViewHas('featured', fn (Collection $featured): bool => $featured->count() === 2)
            ->assertViewHas('newest', fn (Collection $newest): bool => $newest->count() === 4)
            ->assertViewHas('updated', fn (Collection $updated): bool => $updated->count() === 4);
    });
});

describe('homepage recent comment activity', function (): void {
    it('should display recent clean comments on published mods', function (): void {
        $mod = Mod::factory()->create();
        Comment::factory()->count(3)->recycle($mod)->withVersion()->create();

        Livewire::test('pages::homepage')
            ->assertViewHas('recentComments', fn (Collection $comments): bool => $comments->count() === 3);
    });

    it('should not display spam comments', function (): void {
        Queue::fake();

        $mod = Mod::factory()->create();
        Comment::factory()->count(2)->recycle($mod)->withVersion()->create();
        Comment::factory()->recycle($mod)->withVersion()->create(['spam_status' => SpamStatus::SPAM]);

        Livewire::test('pages::homepage')
            ->assertViewHas('recentComments', fn (Collection $comments): bool => $comments->count() === 2);
    });

    it('should not display deleted comments', function (): void {
        $mod = Mod::factory()->create();
        Comment::factory()->count(2)->recycle($mod)->withVersion()->create();
        Comment::factory()->recycle($mod)->withVersion()->create(['deleted_at' => now()]);

        Livewire::test('pages::homepage')
            ->assertViewHas('recentComments', fn (Collection $comments): bool => $comments->count() === 2);
    });

    it('should not display comments on disabled mods', function (): void {
        $enabledMod = Mod::factory()->create();
        $disabledMod = Mod::factory()->disabled()->create();
        Comment::factory()->count(2)->recycle($enabledMod)->withVersion()->create();
        Comment::factory()->recycle($disabledMod)->withVersion()->create();

        Livewire::test('pages::homepage')
            ->assertViewHas('recentComments', fn (Collection $comments): bool => $comments->count() === 2);
    });

    it('should limit to 6 comments', function (): void {
        $mod = Mod::factory()->create();
        Comment::factory()->count(10)->recycle($mod)->withVersion()->create();

        Livewire::test('pages::homepage')
            ->assertViewHas('recentComments', fn (Collection $comments): bool => $comments->count() === 6);
    });

    it('should order comments by newest first', function (): void {
        $mod = Mod::factory()->create();
        $older = Comment::factory()->recycle($mod)->withVersion()->create(['created_at' => now()->subDays(2)]);
        $newer = Comment::factory()->recycle($mod)->withVersion()->create(['created_at' => now()->subDay()]);

        Livewire::test('pages::homepage')
            ->assertViewHas('recentComments', fn (Collection $comments): bool => $comments->first()->id === $newer->id && $comments->last()->id === $older->id);
    });
});
