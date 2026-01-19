<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Dependency;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\VirusTotalLink;
use App\Rules\DirectDownloadLink;
use App\Rules\Semver as SemverRule;
use App\Rules\SemverConstraint as SemverConstraintRule;
use Composer\Semver\Semver;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Session;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;
use Stevebauman\Purify\Facades\Purify;

new #[Layout('layouts::base')] class extends Component {
    use UsesSpamProtection;

    /**
     * The addon version to edit.
     */
    public AddonVersion $addonVersion;

    /**
     * The honeypot data to be validated.
     */
    public HoneypotData $honeypotData;

    /**
     * The version number.
     */
    #[Validate(['required', 'string', 'max:50', new SemverRule()])]
    public string $version = '';

    /**
     * The description of the addon version.
     */
    #[Validate('required|string')]
    public string $description = '';

    /**
     * The link to the addon version.
     */
    public string $link = '';

    /**
     * The mod version constraint.
     */
    #[Validate(['required', 'string', 'max:75', new SemverConstraintRule()])]
    public string $modVersionConstraint = '';

    /**
     * The links to the virus total scans of the addon version.
     *
     * @var array<int, array{url: string, label: string}>
     */
    public array $virusTotalLinks = [];

    /**
     * The published at date of the addon version.
     */
    #[Validate('nullable|date')]
    public ?string $publishedAt = null;

    /**
     * The matching mod versions for the current constraint.
     *
     * @var array<int, array{version: string, id: int}>
     */
    public array $matchingModVersions = [];

    /**
     * The mod dependencies for this addon version.
     *
     * @var array<int, array{id: ?int, modId: string, constraint: string}>
     */
    public array $dependencies = [];

    /**
     * The matching versions for each dependency constraint.
     *
     * @var array<int, array<int, array{mod_name: string, version: string}>>
     */
    public array $matchingDependencyVersions = [];

    /**
     * The DirectDownloadLink rule instance (for content length extraction).
     */
    private DirectDownloadLink $downloadLinkRule;

    /**
     * Mount the component.
     */
    public function mount(Addon $addon, AddonVersion $addonVersion): void
    {
        $this->honeypotData = new HoneypotData();

        $this->addonVersion = $addonVersion->loadMissing('addon.mod');

        $this->authorize('update', $this->addonVersion);

        $this->version = $this->addonVersion->version;
        $this->description = $this->addonVersion->description ?? '';
        $this->link = $this->addonVersion->link;
        $this->modVersionConstraint = $this->addonVersion->mod_version_constraint;

        // Load existing VirusTotal links
        $this->virusTotalLinks = $addonVersion->virusTotalLinks
            ->map(
                fn(VirusTotalLink $link): array => [
                    'url' => $link->url,
                    'label' => $link->label ?? '',
                ],
            )
            ->all();

        // Ensure at least one empty link field is present
        if (empty($this->virusTotalLinks)) {
            $this->virusTotalLinks[] = ['url' => '', 'label' => ''];
        }

        // Load existing dependencies
        $this->dependencies = $addonVersion->dependencies
            ->map(
                fn(Dependency $dependency): array => [
                    'id' => $dependency->id,
                    'modId' => (string) $dependency->dependent_mod_id,
                    'constraint' => $dependency->constraint,
                ],
            )
            ->all();

        // Update matching versions for each existing dependency
        foreach ($this->dependencies as $index => $dependency) {
            $this->updateMatchingDependencyVersions($index);
        }

        $this->publishedAt = $this->addonVersion->published_at
            ? Date::parse($this->addonVersion->published_at)
                ->setTimezone(auth()->user()->timezone ?? 'UTC')
                ->format('Y-m-d\TH:i')
            : null;

        $this->updatedModVersionConstraint();
    }

    /**
     * Preview markdown content.
     */
    #[Renderless]
    public function previewMarkdown(string $content, string $purifyConfig = 'description'): string
    {
        if (empty(mb_trim($content))) {
            return '<p class="text-slate-400 dark:text-slate-500 italic">' . __('Nothing to preview.') . '</p>';
        }

        $converter = new GithubFlavoredMarkdownConverter();
        $html = $converter->convert($content)->getContent();

        return Purify::config($purifyConfig)->clean($html);
    }

    /**
     * Add a new VirusTotal link field.
     */
    public function addVirusTotalLink(): void
    {
        $this->virusTotalLinks[] = ['url' => '', 'label' => ''];
    }

    /**
     * Remove a VirusTotal link field.
     */
    public function removeVirusTotalLink(int $index): void
    {
        unset($this->virusTotalLinks[$index]);
        $this->virusTotalLinks = array_values($this->virusTotalLinks);
    }

    /**
     * Add a new dependency.
     */
    public function addDependency(): void
    {
        $this->dependencies[] = ['id' => null, 'modId' => '', 'constraint' => ''];
    }

    /**
     * Remove a dependency.
     */
    public function removeDependency(int $index): void
    {
        unset($this->dependencies[$index]);
        unset($this->matchingDependencyVersions[$index]);
        $this->dependencies = array_values($this->dependencies);
        $this->matchingDependencyVersions = array_values($this->matchingDependencyVersions);
    }

    /**
     * Update the mod ID for a dependency via JavaScript event.
     */
    #[Renderless]
    public function updateDependencyModId(int $index, ?int $modId): void
    {
        if (isset($this->dependencies[$index])) {
            $this->dependencies[$index]['modId'] = $modId !== null ? (string) $modId : '';
            $this->updateMatchingDependencyVersions($index);
        }
    }

    /**
     * Update the matching dependency versions when the constraint changes.
     */
    public function updatedDependencies(mixed $value, string $path): void
    {
        if (str_ends_with($path, '.constraint')) {
            $index = (int) explode('.', $path)[0];
            $this->updateMatchingDependencyVersions($index);
        }
    }

    /**
     * Update matching dependency versions for a specific dependency.
     */
    private function updateMatchingDependencyVersions(int $index): void
    {
        if (!isset($this->dependencies[$index])) {
            return;
        }

        $dependency = $this->dependencies[$index];
        $modId = $dependency['modId'];
        $constraint = $dependency['constraint'];

        if (empty($modId) || empty($constraint)) {
            $this->matchingDependencyVersions[$index] = [];

            return;
        }

        try {
            $mod = Mod::query()->find($modId);
            if (!$mod) {
                $this->matchingDependencyVersions[$index] = [];

                return;
            }

            $modVersions = ModVersion::query()->where('mod_id', $modId)->where('disabled', false)->whereNotNull('published_at')->get();

            $validVersions = $modVersions->pluck('version')->toArray();
            $compatibleVersions = Semver::satisfiedBy($validVersions, $constraint);

            $this->matchingDependencyVersions[$index] = $modVersions
                ->whereIn('version', $compatibleVersions)
                ->sortByDesc('version')
                ->map(
                    fn(ModVersion $version): array => [
                        'mod_name' => $mod->name,
                        'version' => $version->version,
                    ],
                )
                ->values()
                ->all();
        } catch (\Exception) {
            $this->matchingDependencyVersions[$index] = [];
        }
    }

    /**
     * Update the matching mod versions when the constraint changes.
     */
    public function updatedModVersionConstraint(): void
    {
        if (empty($this->modVersionConstraint) || !$this->addonVersion->addon->mod_id) {
            $this->matchingModVersions = [];

            return;
        }

        try {
            $modVersions = ModVersion::query()
                ->where('mod_id', $this->addonVersion->addon->mod_id)
                ->where('disabled', false)
                ->whereNotNull('published_at')
                ->get();

            $validVersions = $modVersions->pluck('version')->toArray();
            $compatibleVersions = Semver::satisfiedBy($validVersions, $this->modVersionConstraint);

            $this->matchingModVersions = $modVersions
                ->whereIn('version', $compatibleVersions)
                ->sortByDesc('version')
                ->map(
                    fn(ModVersion $version): array => [
                        'id' => $version->id,
                        'version' => $version->version,
                    ],
                )
                ->values()
                ->all();
        } catch (\Exception) {
            $this->matchingModVersions = [];
        }
    }

    /**
     * Save the addon version.
     */
    public function save(): void
    {
        $this->authorize('update', $this->addonVersion);

        // Validate the honeypot data.
        $this->protectAgainstSpam();

        // Create and configure the DirectDownloadLink rule
        $this->downloadLinkRule = new DirectDownloadLink();

        // Build validation rules
        $rules = [
            'link' => ['required', 'string', 'url', 'starts_with:https://,http://', $this->downloadLinkRule],
            'version' => ['required', 'string', 'max:50', new SemverRule()],
            'description' => 'required|string',
            'modVersionConstraint' => ['required', 'string', 'max:75', new SemverConstraintRule()],
            'publishedAt' => 'nullable|date',
        ];

        // VirusTotal links validation
        $rules['virusTotalLinks'] = 'required|array|min:1';
        $rules['virusTotalLinks.*.url'] = 'required|string|url|starts_with:https://www.virustotal.com/';
        $rules['virusTotalLinks.*.label'] = 'nullable|string|max:255';

        // Dependencies validation (optional)
        $rules['dependencies'] = 'array';
        $rules['dependencies.*.modId'] = 'required_with:dependencies.*.constraint|exists:mods,id';
        $rules['dependencies.*.constraint'] = ['required_with:dependencies.*.modId', new SemverConstraintRule()];

        // Build custom messages
        $messages = [
            'virusTotalLinks.required' => 'At least one VirusTotal link is required.',
            'virusTotalLinks.min' => 'At least one VirusTotal link is required.',
            'virusTotalLinks.*.url.required' => 'Please enter a valid VirusTotal URL.',
            'virusTotalLinks.*.url.url' => 'Please enter a valid URL (e.g., https://www.virustotal.com/...).',
            'virusTotalLinks.*.url.starts_with' => 'The URL must start with https://www.virustotal.com/',
            'virusTotalLinks.*.label.max' => 'The label must not exceed 255 characters.',
            'dependencies.*.modId.required_with' => 'Please select a mod for this dependency.',
            'dependencies.*.modId.exists' => 'The selected mod does not exist.',
            'dependencies.*.constraint.required_with' => 'Please enter a version constraint for this dependency.',
        ];

        // Validate all fields
        $this->validate($rules, $messages);

        // Parse the published at date in the user's timezone, convert to UTC for DB storage.
        // Zero out seconds for consistency with datetime-local input format.
        $publishedAtCarbon = null;
        $userTimezone = auth()->user()->timezone ?? 'UTC';
        if ($this->publishedAt !== null) {
            $publishedAtCarbon = Date::parse($this->publishedAt, $userTimezone)->setTimezone('UTC')->second(0);
        }

        $this->addonVersion->update([
            'version' => $this->version,
            'description' => $this->description,
            'link' => $this->link,
            'content_length' => $this->downloadLinkRule->contentLength,
            'mod_version_constraint' => $this->modVersionConstraint,
            'published_at' => $publishedAtCarbon,
        ]);

        // Update VirusTotal links - delete existing and recreate
        $this->addonVersion->virusTotalLinks()->delete();
        foreach ($this->virusTotalLinks as $virusTotalLink) {
            if (!empty($virusTotalLink['url'])) {
                $this->addonVersion->virusTotalLinks()->create([
                    'url' => $virusTotalLink['url'],
                    'label' => !empty($virusTotalLink['label']) ? $virusTotalLink['label'] : '',
                ]);
            }
        }

        // Update dependencies - delete existing and recreate
        $this->addonVersion->dependencies()->delete();
        foreach ($this->dependencies as $dependency) {
            if (!empty($dependency['modId']) && !empty($dependency['constraint'])) {
                $this->addonVersion->dependencies()->create([
                    'dependent_mod_id' => (int) $dependency['modId'],
                    'constraint' => $dependency['constraint'],
                ]);
            }
        }

        Track::event(TrackingEventType::ADDON_VERSION_EDIT, $this->addonVersion);

        Session::flash('success', 'Addon Version has been Successfully Updated');

        $this->redirect($this->addonVersion->addon->detail_url);
    }
};
?>

<x-slot:title>
    {!! __('Edit Addon Version for :addon - The Forge', ['addon' => $addonVersion->addon->name]) !!}
</x-slot>

<x-slot:description>
    {!! __('Update this version for the :addon addon.', ['addon' => $addonVersion->addon->name]) !!}
</x-slot>

<x-slot:header>
    <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight flex items-center gap-2">
        <flux:icon.puzzle-piece class="w-5 h-5" />
        {{ __('Edit Addon Version') }}: {{ $addonVersion->addon->name }}
    </h2>
</x-slot>

<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1 px-4 sm:px-0">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Version Information</h3>
                <p class="my-2 text-sm text-gray-600 dark:text-gray-400">
                    Update this version for <strong>{{ $addonVersion->addon->name }}</strong>. Specify which mod
                    versions this
                    addon version is compatible with using semver constraints.
                </p>
            </div>

            <div class="mt-5 md:mt-0 md:col-span-2">
                <form wire:submit="save">
                    <div class="px-4 py-5 bg-white dark:bg-gray-900 sm:p-6 shadow-sm sm:rounded-tl-md sm:rounded-tr-md">
                        <div class="grid grid-cols-6 gap-6">
                            @csrf

                            {{-- Version --}}
                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Version Number') }}</flux:label>
                                <flux:description>{!! __('The version number for this release. Must follow semantic versioning.') !!}</flux:description>
                                <flux:input
                                    type="text"
                                    wire:model.blur="version"
                                    placeholder="1.0.0"
                                    maxlength="50"
                                />
                                <flux:error name="version" />
                            </flux:field>

                            {{-- Description --}}
                            <flux:field class="col-span-6">
                                <x-markdown-editor
                                    wire-model="description"
                                    name="description"
                                    :label="__('Description')"
                                    :description="__(
                                        'Explain what\'s new or changed in this version. Use markdown for formatting.',
                                    )"
                                    placeholder="This version includes updates to the..."
                                    rows="6"
                                    purify-config="description"
                                />
                            </flux:field>

                            {{-- Download Link --}}
                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Download Link') }}</flux:label>
                                <flux:description>
                                    {{ __('Provide a direct download link to the addon file. The addon archive must follow the structure specified in the file submission guidelines or the launcher will not support automatic installs or updates for your addon.') }}
                                </flux:description>
                                <flux:input
                                    type="url"
                                    wire:model.blur="link"
                                    placeholder="https://www.example.com/your-addon-archive.7zip"
                                />
                                <flux:error name="link" />
                            </flux:field>

                            {{-- Mod Version Constraint --}}
                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Mod Version Constraint') }}</flux:label>
                                <flux:description>{!! __(
                                    'Specify which mod versions this addon version is compatible with using semantic version constraints. For example, you can use the value ~1.0.0 to match all 1.0 versions. Works just like Composer or NPM. Start typing to see matches below.',
                                ) !!}</flux:description>
                                <flux:input
                                    type="text"
                                    wire:model.live.debounce="modVersionConstraint"
                                    placeholder="~1.0.0"
                                />
                                <flux:error name="modVersionConstraint" />
                                @if (count($matchingModVersions) > 0)
                                    <div class="mt-2 space-y-1">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ __('Current Matching Mod Versions:') }}</p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($matchingModVersions as $version)
                                                <span
                                                    class="inline-flex items-center rounded-md bg-gray-100 dark:bg-gray-800 px-2 py-1 text-xs font-medium text-gray-600 dark:text-gray-400"
                                                >
                                                    {{ $version['version'] }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </flux:field>

                            {{-- Mod Dependencies --}}
                            <flux:field class="col-span-6">
                                <flux:label badge="Optional">{{ __('Mod Dependencies') }}</flux:label>
                                <flux:description>
                                    {{ __('Specify mods that this addon version depends on. Use semantic version constraints to define compatible versions.') }}
                                </flux:description>

                                <div class="space-y-4">
                                    @foreach ($dependencies as $index => $dependency)
                                        <div
                                            wire:key="dependency-{{ $dependency['id'] ?? $index }}"
                                            class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg"
                                        >
                                            <div class="flex justify-between items-start mb-3">
                                                <span
                                                    class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Dependency #:num', ['num' => $index + 1]) }}</span>
                                                <flux:button
                                                    size="xs"
                                                    variant="outline"
                                                    wire:click="removeDependency({{ $index }})"
                                                    type="button"
                                                >
                                                    {{ __('Remove') }}
                                                </flux:button>
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <flux:field>
                                                    <flux:label>{{ __('Mod') }}</flux:label>
                                                    <livewire:form.mod-autocomplete
                                                        :key="'autocomplete-' . ($dependency['id'] ?? $index)"
                                                        :selected-mod-id="$dependencies[$index]['modId'] ?? ''"
                                                        placeholder="Type to search for a mod..."
                                                        label="Select dependency mod"
                                                        @mod-selected="updateDependencyModId({{ $index }}, $event.detail.modId)"
                                                    />
                                                    <flux:error name="dependencies.{{ $index }}.modId" />
                                                </flux:field>

                                                <flux:field>
                                                    <flux:label>{{ __('Version Constraint') }}</flux:label>
                                                    <flux:input
                                                        type="text"
                                                        wire:model.live.debounce="dependencies.{{ $index }}.constraint"
                                                        placeholder="~1.0.0"
                                                    />
                                                    <flux:error name="dependencies.{{ $index }}.constraint" />
                                                </flux:field>
                                            </div>

                                            @if (isset($matchingDependencyVersions[$index]) && count($matchingDependencyVersions[$index]) > 0)
                                                <div class="mt-3">
                                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                                        {{ __('Matching Versions:') }}</p>
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach ($matchingDependencyVersions[$index] as $version)
                                                            <span
                                                                class="inline-flex items-center rounded-md bg-gray-100 dark:bg-gray-800 px-2 py-1 text-xs font-medium text-gray-600 dark:text-gray-400"
                                                            >
                                                                {{ $version['mod_name'] }} v{{ $version['version'] }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @elseif(!empty($dependencies[$index]['modId']) && !empty($dependencies[$index]['constraint']))
                                                <div class="mt-3">
                                                    <p class="text-sm text-yellow-600 dark:text-yellow-400">
                                                        {{ __('No matching versions found for this constraint.') }}</p>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach

                                    <flux:button
                                        size="sm"
                                        variant="outline"
                                        wire:click="addDependency"
                                        type="button"
                                    >
                                        {{ __('+ Add Dependency') }}
                                    </flux:button>
                                </div>
                            </flux:field>

                            {{-- VirusTotal Links --}}
                            <flux:field class="col-span-6">
                                <flux:label>{{ __('VirusTotal Links') }}</flux:label>
                                <flux:description>{!! __(
                                    'Provide links to the <a href="https://www.virustotal.com" target="_blank" class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors">VirusTotal</a> scan results for your addon files. This helps users verify the safety of your addon. At least one link is required.',
                                ) !!}</flux:description>

                                <div class="space-y-3">
                                    @foreach ($virusTotalLinks as $index => $virusTotalLink)
                                        <div class="flex gap-2 items-center">
                                            <div class="flex-1">
                                                <flux:input
                                                    type="url"
                                                    wire:model.blur="virusTotalLinks.{{ $index }}.url"
                                                    placeholder="https://www.virustotal.com/..."
                                                />
                                            </div>
                                            <div class="w-40">
                                                <flux:input
                                                    type="text"
                                                    wire:model.blur="virusTotalLinks.{{ $index }}.label"
                                                    placeholder="Label (optional)"
                                                />
                                            </div>
                                            @if ($index > 0)
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    wire:click="removeVirusTotalLink({{ $index }})"
                                                    type="button"
                                                    icon="x-mark"
                                                />
                                            @endif
                                        </div>
                                        @error('virusTotalLinks.' . $index . '.url')
                                            <flux:error>{{ $message }}</flux:error>
                                        @enderror
                                    @endforeach

                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        wire:click="addVirusTotalLink"
                                        type="button"
                                        icon="plus"
                                    >
                                        {{ __('Add Link') }}
                                    </flux:button>
                                </div>

                                <flux:error name="virusTotalLinks" />
                            </flux:field>

                            {{-- Published At --}}
                            <flux:field
                                class="col-span-6"
                                x-data="{
                                    now() {
                                        // Format: YYYY-MM-DDTHH:MM
                                        const pad = n => n.toString().padStart(2, '0');
                                        const d = new Date();
                                        return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
                                    }
                                }"
                            >
                                <flux:label badge="Optional">{{ __('Publish Date') }}</flux:label>
                                <flux:description>
                                    {!! __(
                                        'Select the date and time the addon will be published. If the addon is not published, it will not be discoverable by other users. Leave blank to keep the addon unpublished.',
                                    ) !!}
                                </flux:description>

                                <div class="space-y-3">
                                    @if (auth()->user()->timezone === null)
                                        <flux:callout
                                            icon="exclamation-triangle"
                                            color="orange"
                                            inline="inline"
                                        >
                                            <flux:callout.text>
                                                You have not selected a timezone for your account. You may continue,
                                                but the published date will be interpreted as a UTC date.
                                                Alternatively, you can <a
                                                    href="/user/profile"
                                                    class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors"
                                                >edit your profile</a> to set a specific timezone.
                                            </flux:callout.text>
                                        </flux:callout>
                                    @else
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ __('Your timezone is set to :timezone.', ['timezone' => auth()->user()->timezone]) }}
                                        </p>
                                    @endif
                                    <div class="flex gap-2 items-center">
                                        <flux:input
                                            type="datetime-local"
                                            wire:model.defer="publishedAt"
                                            placeholder="Leave blank to keep unpublished"
                                        />
                                        @if (auth()->user()->timezone !== null)
                                            <flux:button
                                                size="sm"
                                                variant="outline"
                                                @click="$wire.set('publishedAt', now())"
                                            >Now</flux:button>
                                        @endif
                                    </div>
                                </div>

                                <flux:error name="publishedAt" />
                            </flux:field>

                            {{-- Honeypot --}}
                            <x-honeypot />
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-end px-4 py-3 bg-gray-50 dark:bg-gray-900 border-t-2 border-transparent dark:border-t-gray-700 text-end sm:px-6 shadow-sm sm:rounded-bl-md sm:rounded-br-md gap-4">
                        <flux:button
                            variant="primary"
                            size="sm"
                            class="my-1.5 text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700"
                            type="submit"
                        >{{ __('Update Version') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
