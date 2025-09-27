<x-layouts.static-toc>
    <x-slot name="pageTitle">{{ __('Content Guidelines') }}</x-slot>

    <x-slot name="pageDescription">{{ __('Content Guidelines for The Forge') }}</x-slot>

    <x-slot name="tableOfContents">
        <x-table-of-contents-item href="#overview" title="Overview" />
        <x-table-of-contents-item href="#general-submission" title="General Submission Requirements">
            <x-table-of-contents-subitem href="#file-format-standards">File Format Standards</x-table-of-contents-subitem>
            <x-table-of-contents-subitem href="#mod-types-requirements">Mod Types and Requirements</x-table-of-contents-subitem>
        </x-table-of-contents-item>
        <x-table-of-contents-item href="#semantic-versioning" title="Semantic Versioning Requirements">
            <x-table-of-contents-subitem href="#understanding-semver">Understanding Semantic Versioning</x-table-of-contents-subitem>
            <x-table-of-contents-subitem href="#version-constraints">Semantic Version Constraints</x-table-of-contents-subitem>
            <x-table-of-contents-subitem href="#implementation-requirements">Implementation Requirements</x-table-of-contents-subitem>
        </x-table-of-contents-item>
        <x-table-of-contents-item href="#quality-standards" title="Content Quality Standards">
            <x-table-of-contents-subitem href="#functional-requirements">Functional Requirements</x-table-of-contents-subitem>
            <x-table-of-contents-subitem href="#code-quality">Code Quality Standards</x-table-of-contents-subitem>
        </x-table-of-contents-item>
        <x-table-of-contents-item href="#executable-security" title="Executable Files and Security">
            <x-table-of-contents-subitem href="#executable-requirements">Executable Content Requirements</x-table-of-contents-subitem>
            <x-table-of-contents-subitem href="#network-communication">Network Communication</x-table-of-contents-subitem>
        </x-table-of-contents-item>
        <x-table-of-contents-item href="#licensing-permissions" title="Content Licensing and Permissions">
            <x-table-of-contents-subitem href="#license-requirements">License Requirements</x-table-of-contents-subitem>
            <x-table-of-contents-subitem href="#attribution-requirements">Attribution Requirements</x-table-of-contents-subitem>
        </x-table-of-contents-item>
        <x-table-of-contents-item href="#special-categories" title="Special Content Categories">
            <x-table-of-contents-subitem href="#adult-content">Adult Content Policy</x-table-of-contents-subitem>
            <x-table-of-contents-subitem href="#anti-cheat-policy">Anti-Cheat and Exploit Policy</x-table-of-contents-subitem>
            <x-table-of-contents-subitem href="#compilation-guidelines">Compilation and Collection Guidelines</x-table-of-contents-subitem>
            <x-table-of-contents-subitem href="#payment-policy">Payment and Commercial Activity Policy</x-table-of-contents-subitem>
        </x-table-of-contents-item>
        <x-table-of-contents-item href="#file-hosting" title="File Hosting and Distribution" />
        <x-table-of-contents-item href="#violation-consequences" title="Violation Consequences and Appeals" />
    </x-slot>

    <p><strong>Effective Date:</strong> August 26, 2025<br><strong>Last Updated:</strong> September 26, 2025</p>

    <h2 id="overview">1. Overview</h2>
    <p>These Content Guidelines establish the technical and quality standards for all files, mods, and content submitted to The Forge. Following these guidelines ensures a consistent, professional experience for all users and maintains the integrity of our modding platform.</p>
    <p><strong>What These Guidelines Cover:</strong></p>
    <ul>
        <li>File submission requirements and technical standards</li>
        <li>Mod versioning standards for consistency and compatibility</li>
        <li>Content licensing and permissions</li>
        <li>Special requirements for different content types</li>
    </ul>

    <h2 id="general-submission">2. General Submission Requirements</h2>

    <h3 id="file-format-standards">2.1 File Format Standards</h3>
    <p><strong>Archive Requirements:</strong></p>
    <p>The Forge maintains strict archive standards to ensure consistency and ease of installation across all submitted content. These requirements help prevent compatibility issues and streamline the user experience.</p>
    <ul>
        <li>All mods must be packaged in 7-Zip format (<code>.7z</code> only) to ensure consistent compression and extraction behavior across different operating systems and by different tooling</li>
        <li>Archives must contain all necessary files for mod functionality, ensuring users receive complete packages without missing external dependencies</li>
        <li>Password-protected archives are prohibited to prevent access issues and maintain security transparency</li>
    </ul>
    <p><strong>File Structure:</strong></p>
    <p>The file structure within archives must provide an installation-ready layout that matches SPT directory conventions. Users should be able to extract the archive contents and place them directly into their SPT root directory without requiring additional folder manipulation or reorganization. This approach minimizes installation complexity and reduces support requests related to improper file placement.</p>
    <p><strong>Description Requirements:</strong></p>
    <p>Every submission must include clear installation instructions presented in step-by-step format and basic usage instructions or configuration guidance. Any required dependencies must be added to each mod version submission. These requirements ensure that users can successfully install and configure mods regardless of their technical expertise.</p>

    <h3 id="mod-types-requirements">2.2 Mod Types and Requirements</h3>
    <h4>Client Mods (BepInEx Plugins):</h4>
    <p>Client-side modifications require additional scrutiny due to their direct interaction with game code. The following requirements ensure code quality and maintainability while enabling proper compatibility tracking.</p>
    <ul>
        <li>A link to the source code is required</li>
        <li>Compiled files must be included and ready for immediate use without requiring users to build from source</li>
        <li>
            The <code>[BepInPlugin]</code> attribute must be present on the class that extends <code>BaseUnityPlugin</code> with the following properties:
            <ul>
                <li><strong>GUID ("com.username.modname"):</strong> Must match the GUID entered when uploading to The Forge and the GUID in the server mod (if one exists) to ensure proper mod identification</li>
                <li><strong>Mod Name ("Username-ModName"):</strong> Must start with your username, followed by a dash, followed by the mod name, with only letters, numbers, and a single dash allowed for consistent naming conventions</li>
                <li><strong>Version ("1.2.3"):</strong> Must be a valid semantic version and match the version in the server mod (if one exists) to maintain synchronization</li>
            </ul>
            <pre><code class="language-csharp">[BepInPlugin("com.username.modname", "Username-ModName", "1.2.3")]</code></pre>
        </li>
    </ul>
    <h4>Server Mods (SPT v3.x and below - Node.js):</h4>
    <p>Server modifications for legacy SPT versions must follow established Node.js conventions while maintaining compatibility with the SPT server architecture. These requirements ensure proper integration and prevent conflicts with core SPT functionality.</p>
    <ul>
        <li>Files must be properly packaged as JavaScript or TypeScript following SPT server mod structure and conventions</li>
        <li>Modifications should alter data in memory during runtime rather than directly editing core SPT files to maintain system stability</li>
        <li>
            The <code>package.json</code> file must include required properties:
            <ul>
                <li><strong>version</strong>: Must be a valid semantic version and match the client mod version (if one exists) to ensure compatibility</li>
                <li><strong>sptVersion</strong>: Semantic version constraint specifying SPT compatibility range for automatic compatibility checking</li>
            </ul>
            <pre><code class="language-json">{
  "version": "1.2.3",
  "sptVersion": "~3.11"
}</code></pre>
            <em>See <a href="https://github.com/sp-tarkov/server-mod-examples/">mod examples</a> for more information</em>
        </li>
    </ul>
    <h4>Server Mods (SPT v4.0+ - C#):</h4>
    <p>Modern SPT server mods utilize C# and require additional metadata for proper integration with the new modding framework. These requirements ensure compatibility with SPT's enhanced mod management system.</p>
    <ul>
        <li>Source code links are required for security review and community development</li>
        <li>Compiled files must be included and ready for use without requiring compilation</li>
        <li>Modifications should follow SPT server mod structure and conventions, modifying data in memory during runtime rather than editing core SPT files directly</li>
        <li>
            Mod version must be specified in two locations for consistency:
            <ul>
                <li><code>.csproj</code> file: <code>&lt;Version&gt;1.2.3&lt;/Version&gt;</code> tag for build system integration</li>
                <li>
                    <code>AbstractModMetadata</code> properties for runtime identification:
                    <pre><code class="language-csharp">// Must match the GUID entered when uploading to The Forge and the GUID in the client mod (if one exists):
public override string ModGuid { get; init; } = "com.sp-tarkov.examples.editdatabase";

// Only include letters and numbers in the Name and Author properties:
public override string Name { get; init; } = "EditDatabaseExample";
public override string Author { get; init; } = "SPTarkov";

// Define your mod version here:
public override SemanticVersioning.Version Version { get; } = new("1.2.3");

// Semantic version constraint for SPT compatibility:
public override SemanticVersioning.Version SptVersion { get; } = new("4.0.0");</code></pre>
                </li>
            </ul>
            <em>See <a href="https://github.com/sp-tarkov/server-mod-examples/">mod examples</a> for more information</em>
        </li>
    </ul>
    <p><strong>Configuration Presets:</strong></p>
    <p>Configuration modifications represent significant changes to game behavior and require careful documentation to ensure users understand their impact and can troubleshoot issues effectively.</p>
    <ul>
        <li>Files may include JSON, XML, or other configuration formats with significant modifications to default settings</li>
        <li>Submissions must include clear descriptions of all changes made and their expected effects on gameplay</li>
        <li>Base game version and mod dependencies must be specified to prevent compatibility issues</li>
        <li>Special installation requirements must be documented with step-by-step instructions for proper setup</li>
        <li>Uninstallation procedures must be provided to help users revert changes if needed</li>
    </ul>
    <p><strong>Tools and Utilities:</strong></p>
    <p>Standalone tools and utilities extend SPT functionality beyond traditional mods and require additional verification to ensure they operate safely within the SPT ecosystem.</p>
    <ul>
        <li>Executable files (<code>.exe</code>, <code>.cmd</code>, <code>.bat</code>, etc.) must be provided in ready-to-use format</li>
        <li>Source code links are required for security verification and transparency</li>
        <li>Special installation requirements must be thoroughly documented with clear instructions</li>
        <li>Removal procedures must be provided to help users cleanly uninstall tools when no longer needed</li>
    </ul>

    <h2 id="semantic-versioning">3. Semantic Versioning Requirements</h2>

    <h3 id="understanding-semver">3.1 Understanding Semantic Versioning</h3>
    <p>All mods submitted to The Forge must use Semantic Versioning (SemVer) for version numbering. SemVer is a widely-adopted standard that provides clear meaning to version numbers and helps users understand the nature of changes between releases. The complete specification is available at <a href="https://semver.org/">semver.org</a>.</p>
    <p><strong>Basic SemVer Format: <code>MAJOR.MINOR.PATCH</code></strong></p>
    <p>Each component serves a specific purpose in communicating the type of changes included in a release. The MAJOR version increments for incompatible changes that break existing functionality or require user intervention. The MINOR version increments when new functionality is added in a backward-compatible manner. The PATCH version increments for backward-compatible bug fixes and minor improvements.</p>
    <p>Examples of properly formatted semantic versions include <code>1.0.0</code> for an initial stable release, <code>2.1.3</code> for a version with significant features and multiple patches, or <code>0.8.2</code> for pre-release development versions. Pre-release versions may include additional identifiers such as <code>1.0.0-beta.1</code> or <code>2.0.0-rc.2</code>.</p>
    <p><strong>When to Increment Version Components:</strong></p>
    <p>Increment the MAJOR version when making incompatible changes that require users to modify their configurations, when removing features or functionality that existing users depend on, when changing mod dependencies in ways that break existing installations, or when restructuring the mod in ways that affect how users install or configure it.</p>
    <p>Increment the MINOR version when adding new features that maintain compatibility with existing configurations, when introducing new configuration options that have sensible defaults, when adding optional dependencies that enhance but do not require existing functionality, or when making significant improvements that do not break existing usage patterns.</p>
    <p>Increment the PATCH version when fixing bugs without changing functionality, when making performance optimizations, when updating documentation or help text, when making internal code improvements that do not affect user experience, or when addressing security issues that do not change the mod's interface.</p>

    <h3 id="version-constraints">3.2 Semantic Version Constraints</h3>
    <p>A semantic version constraint defines which versions of a dependency are acceptable for your mod to function correctly. Constraints are expressions that specify compatibility ranges rather than exact version matches, allowing for automatic updates within safe boundaries while preventing incompatible versions from being used.</p>
    <p><strong>Common Constraint Patterns:</strong></p>
    <p>The tilde constraint (<code>~1.2.3</code>) allows patch-level changes within the same minor version, meaning it accepts <code>1.2.3</code>, <code>1.2.4</code>, and <code>1.2.15</code> but rejects <code>1.3.0</code>. This constraint is useful when you want to receive bug fixes but avoid new features that might introduce compatibility issues.</p>
    <p>The caret constraint (<code>^1.2.3</code>) allows minor and patch-level changes within the same major version, accepting <code>1.2.3</code>, <code>1.3.0</code>, and <code>1.9.5</code> but rejecting <code>2.0.0</code>. This constraint is appropriate when you can handle new features but not breaking changes.</p>
    <p>Exact version matching (<code>1.2.3</code>) requires precisely that version and no other. This approach provides maximum stability but prevents users from receiving any updates, including critical bug fixes. Range constraints (<code>&gt;=1.2.0 &lt;2.0.0</code>) offer more flexibility by specifying minimum and maximum acceptable versions.</p>
    <p><strong>Practical Application in Mod Development:</strong></p>
    <p>When specifying SPT compatibility, use constraints that reflect the actual compatibility testing you have performed. If your mod has been tested with SPT 3.11 and you expect it to work with future patch releases, use <code>~3.11.0</code>. If your mod uses features introduced in SPT 4.0 but should work with future minor releases, use <code>^4.0.0</code>.</p>
    <p>For mod dependencies, consider the stability and development practices of the dependencies you rely on. Well-maintained mods with consistent APIs may safely use caret constraints, while experimental or rapidly-changing dependencies may require more restrictive constraints.</p>

    <h3 id="implementation-requirements">3.3 Implementation Requirements</h3>
    <p><strong>Version Declaration Consistency:</strong></p>
    <p>All version numbers declared within a single mod must match exactly. Client-side plugins, server-side modules, and any associated configuration files must declare identical version numbers. This consistency ensures that users can clearly identify complete mod packages and ensures that automatic tooling can reliably select mods based on their version.</p>
    <p><strong>SPT Compatibility Constraints:</strong></p>
    <p>Every server mod must declare its SPT version compatibility using appropriate constraint syntax. Server mods for SPT v3.x must include the <code>sptVersion</code> field in their <code>package.json</code> file. Server mods for SPT v4.0+ must specify the <code>SptVersion</code> property in their metadata class. These constraints should reflect actual testing and validation performed by the mod author.</p>
    <p><strong>Version Validation:</strong></p>
    <p>The Forge automatically validates semantic version format compliance during the submission process. Improperly formatted versions will be rejected. Pre-release versions are acceptable for beta or experimental content but must follow the SemVer pre-release specification exactly.</p>

    <h2 id="quality-standards">4. Content Quality Standards</h2>

    <h3 id="functional-requirements">4.1 Functional Requirements</h3>
    <p><strong>Testing Standards:</strong></p>
    <p>Mod authors must thoroughly test their submissions using a fresh SPT installation (with all documented dependencies properly installed) to ensure compatibility and stability before making them available to the community. All advertised features must work as described. The mod must load without errors, and without causing unintended changes to base SPT functionality. Testing should verify that the mod functions correctly in standard user environments without requiring undocumented system configurations or additional modifications.</p>
    <p><strong>Performance Requirements:</strong></p>
    <p>Mods should not cause significant, unintended performance degradation during normal gameplay or system operation. Memory leaks and excessive resource usage are strictly prohibited as they negatively impact user experience and system stability. Loading times should remain reasonable compared to base SPT performance, and mods must not contain infinite loops or blocking operations that could cause system freezes or unresponsive behavior.</p>
    <p><strong>Error Handling:</strong></p>
    <p>Error handling must be implemented gracefully to manage missing dependencies without causing system crashes or data corruption. Clear error messages should be provided for configuration issues to help users identify and resolve problems independently. Fallback behavior should be implemented when possible to maintain basic functionality even when optimal conditions are not met. Logging systems should be designed to help users troubleshoot problems by providing relevant diagnostic information without overwhelming them with excessive technical detail.</p>
    <p><strong>Logging Standards:</strong></p>
    <p>Logging functionality should provide pertinent information to end users while maintaining clean, readable console and file output. Excessive or inappropriate logging can impede users' ability to identify genuine errors or warnings among unnecessary output, degrading the overall user experience and making troubleshooting more difficult.</p>
    <p>Restricted logging practices include any form of ASCII or Unicode art or logos that clutter console output, multi-line mod author or team credits that should be reserved for documentation rather than runtime logs, and advertising of external links that serves no diagnostic purpose. Log messages should focus exclusively on operational status, configuration information, error reporting, and debugging data that helps users understand mod behavior and resolve issues effectively.</p>

    <h3 id="code-quality">4.2 Code Quality Standards</h3>
    <p><strong>Security Requirements:</strong></p>
    <p>No obfuscated code may be present in executable files, as this prevents proper security review and violates transparency standards. Source code must be available for review through publicly accessible repositories that contain the exact code used to generate submitted binaries. No unauthorized network connections or data collection may be implemented without explicit user consent and clear documentation of the purpose and scope of such activities. Modifications to system files outside the SPT directory are prohibited to prevent system instability and security risks.</p>
    <p><strong>AI-Generated Content Policy:</strong></p>
    <p>The Forge does not accept mods that have been substantially or entirely written by AI coding agents. AI models have not been trained on the specific codebase and security requirements necessary to safely modify SPT client and server code. This limitation creates significant risks for code stability, security vulnerabilities, and compatibility issues that AI-generated code cannot adequately address.</p>
    <p>Mod authors must fully understand their code's functionality, implementation details, and potential security implications because they are responsible for how their modifications affect other users' systems and gameplay experience. When users download a mod, they trust that the author has thoroughly reviewed, tested, and validated every aspect of the code's behavior.</p>
    <p>Using AI tools for basic code completion, syntax assistance, or generating small utility functions is acceptable when the author reviews, understands, and takes full responsibility for the generated code. However, using AI to write entire features, complex game modifications, or substantial portions of mod functionality is prohibited. The distinction lies in whether the author maintains complete understanding and control over their code versus relying on AI to generate logic they cannot fully comprehend or validate.</p>
    <p>Authors must be prepared to explain any part of their submitted code, debug issues that arise, and modify functionality as needed. This level of ownership is impossible when AI generates significant portions of the codebase without the author's deep understanding of the implementation details.</p>

    <h2 id="executable-security">5. Executable Files and Security</h2>

    <h3 id="executable-requirements">5.1 Executable Content Requirements</h3>
    <p><strong>Mandatory Requirements:</strong></p>
    <p>Executable content presents the highest security risk and requires comprehensive verification to protect users from malicious software. These requirements ensure transparency and enable proper security review of all executable components.</p>
    <ul>
        <li>All <code>.dll</code>, <code>.exe</code>, and compiled binary files must include a link to publicly accessible source code</li>
        <li>Source code must be available through established platforms (GitHub, GitLab, etc.) that provide proper version control and transparency</li>
        <li>Repositories must contain the exact code used to build the executable, with no hidden or proprietary components</li>
        <li>Build instructions must be provided in the repository to enable independent verification of the compilation process</li>
    </ul>
    <p><strong>Security Verification:</strong></p>
    <ul>
        <li>VirusTotal scan links are required for all executable content to provide initial security screening</li>
        <li>False positives (1-2 detections) are evaluated on a case-by-case basis considering the detection engines and threat classifications</li>
        <li>VirusTotal links must be updated for each version released to ensure current security assessment</li>
        <li>Staff reserves the right to request additional security verification through alternative scanning services or manual review</li>
    </ul>
    <p><strong>Prohibited Executable Behavior:</strong></p>
    <ul>
        <li>Code obfuscation or anti-debugging techniques are prohibited as they prevent proper security analysis</li>
        <li>Unauthorized system modifications outside the SPT directory are forbidden to maintain system integrity</li>
        <li>Data collection or network communication without disclosure violates user privacy expectations</li>
        <li>Installation of additional software or drivers is prohibited to prevent system compromise</li>
    </ul>

    <h3 id="network-communication">5.2 Network Communication</h3>
    <p><strong>Allowed Network Activity:</strong></p>
    <p>Network communication capabilities must be transparent and serve legitimate purposes that benefit users. All network activity requires clear disclosure and appropriate user consent mechanisms.</p>
    <ul>
        <li>Update checks are permitted with user consent and clear disclosure of what information is transmitted</li>
        <li>Crash reporting is allowed when anonymized and implemented with user consent and opt-out options</li>
        <li>API calls essential for mod functionality are acceptable when clearly documented and disclosed in advance</li>
    </ul>
    <p><strong>Required Disclosure:</strong></p>
    <ul>
        <li>All network activity must be documented in detail, including destination servers and data transmitted</li>
        <li>Privacy implications must be clearly explained in language that non-technical users can understand</li>
        <li>Opt-out options must be provided where technically feasible to respect user privacy preferences</li>
    </ul>
    <p><strong>Prohibited Network Activity:</strong></p>
    <ul>
        <li>Unauthorized data collection or telemetry violates user privacy and trust</li>
        <li>Communication with unknown or undisclosed servers presents security risks</li>
        <li>Automatic downloading of additional content without consent can introduce malware or unwanted modifications</li>
        <li>User tracking or analytics without explicit consent violates privacy expectations</li>
    </ul>

    <h2 id="licensing-permissions">6. Content Licensing and Permissions</h2>
    <h3 id="license-requirements">6.1 License Requirements</h3>
    <p><strong>Acceptable Licenses:</strong></p>
    <p>Content licensing ensures legal compliance and clarifies usage rights for both creators and users. The Forge accepts standard open-source and creative licenses that provide appropriate legal frameworks.</p>
    <ul>
        <li>MIT, Apache 2.0, GPL, BSD, and other OSI-approved licenses provide established legal frameworks for code distribution</li>
        <li>Creative Commons licenses are appropriate for non-code content including documentation, artwork, and media files</li>
        <li>Public domain dedication is acceptable for content where authors wish to relinquish all rights</li>
    </ul>
    <p><strong>License Documentation:</strong></p>
    <ul>
        <li>License files must be included in mod archives to ensure users understand their rights and obligations</li>
        <li>Third-party component licenses must be respected and documented to maintain legal compliance</li>
        <li>Authors must understand the implications of their chosen license, particularly regarding commercial use and derivative works</li>
    </ul>

    <h3 id="attribution-requirements">6.2 Attribution Requirements</h3>
    <p><strong>Obtaining Permission:</strong></p>
    <p>Building upon or modifying existing community content requires explicit permission from original creators to respect their intellectual property rights and creative contributions. These requirements protect creators while enabling collaborative development within appropriate boundaries.</p>
    <p>Submission of existing user-contributed content without obtaining permission from the original authors is strictly prohibited. This applies regardless of whether modifications have been made or if the content is being redistributed as-is. The burden of obtaining proper authorization rests entirely with the person submitting derivative or redistributed content.</p>
    <p>Proper credit to original authors must be provided unless the authors have explicitly specified that attribution is not necessary. However, providing attribution alone does not substitute for receiving explicit permission to upload or modify someone else's work. Permission and attribution serve different purposes and both requirements must be satisfied independently.</p>
    <p><strong>When Using Others' Work:</strong></p>
    <p>Proper attribution protects original creators' rights while enabling collaborative development. These requirements ensure credit is given appropriately while maintaining legal compliance.</p>
    <ul>
        <li>Clear credit must be provided to original authors using their preferred attribution format</li>
        <li>Original source links should be included when possible to enable users to find upstream versions</li>
        <li>Specific attribution requirements from original licenses must be respected and implemented correctly</li>
        <li>License information for third-party components must be included to maintain legal compliance</li>
    </ul>

    <h2 id="special-categories">7. Special Content Categories</h2>

    <h3 id="adult-content">7.1 Adult Content Policy</h3>
    <p><strong>Prohibited Content:</strong></p>
    <p>The Forge maintains family-friendly content standards while allowing mature themes appropriate to the source game. These restrictions ensure broad accessibility while respecting community standards.</p>
    <ul>
        <li>Nudity or sexual content of any kind is prohibited to maintain appropriate content standards</li>
        <li>Suggestive or sexually provocative material is not acceptable regardless of context</li>
        <li>Content that sexualizes characters or game elements violates community standards</li>
        <li>References to adult websites or services are prohibited to maintain appropriate boundaries</li>
    </ul>

    <p><strong>Mature Themes:</strong></p>
    <ul>
        <li>Violence and combat modifications are generally acceptable given the tactical nature of the base game</li>
        <li>Realistic tactical or military themes are allowed when appropriate to the game context</li>
        <li>Dark or serious storylines are permitted if appropriate and clearly marked in descriptions</li>
        <li>Language and mature themes should be noted in descriptions to help users make informed choices</li>
    </ul>

    <h3 id="anti-cheat-policy">7.2 Anti-Cheat and Exploit Policy</h3>
    <p><strong>Prohibited Modifications:</strong></p>
    <p>The Forge strictly prohibits content that could be used to gain unfair advantages in multiplayer environments or that resembles traditional cheating tools, even when designed for single-player use.</p>
    <ul>
        <li>Any mod usable in live Escape From Tarkov multiplayer is prohibited to prevent cheating migration</li>
        <li>Traditional "hacks" like ESP, wallhacks, and aimbots are forbidden regardless of stated purpose</li>
        <li>Mods that appear to be cheating tools are prohibited even if designed exclusively for SPT use</li>
        <li>Exploits that could be adapted for multiplayer use present unacceptable risks</li>
    </ul>
    <p><strong>Allowed Development Tools:</strong></p>
    <ul>
        <li>Debug overlays and development menus are acceptable when clearly labelled as development tools</li>
        <li>Testing utilities that require developer mode or special setup serve legitimate development purposes</li>
        <li>Educational tools that demonstrate game mechanics provide valuable learning opportunities</li>
        <li>Diagnostic tools for troubleshooting mod conflicts help maintain a healthy modding ecosystem</li>
    </ul>

    <h3 id="compilation-guidelines">7.3 Compilation and Collection Guidelines</h3>
    <p><strong>Prohibited Content:</strong></p>
    <p>Mod compilations, collections, and modpacks are not permitted on The Forge. While these packages may appear to offer convenience by bundling multiple mods together, they create significant and ongoing maintenance challenges that cannot be sustainably managed.</p>
    <p>Permission tracking becomes unmanageable as compilations require explicit consent from every included mod author. Compatibility maintenance is equally problematic since individual mods within a compilation update independently, often breaking compatibility with other included components. Version synchronization across multiple mods with different release schedules creates constant conflicts that require continuous manual intervention.</p>
    <p>The Forge encourages users to install individual mods directly from their original creators to ensure they receive proper updates, support, and compatibility verification. This approach guarantees that users get the most current versions while supporting mod authors directly and maintaining clear accountability for each component.</p>

    <h3 id="payment-policy">7.4 Payment and Commercial Activity Policy</h3>
    <p><strong>Free Access Requirement:</strong></p>
    <p>The Forge strictly prohibits any form of payment requirement for accessing content within our community. All mods, tools, and resources must remain completely free and accessible to all users without any financial barriers or obligations.</p>
    <p><strong>Prohibited Commercial Activities:</strong></p>
    <ul>
        <li>Requiring payment for mod downloads, early access, or premium versions</li>
        <li>Creating paywalls or subscription models for content access</li>
        <li>Offering goods or services in exchange for payment within our community</li>
        <li>Advertising paid services, commissions, or commercial offerings</li>
        <li>Linking to external sites that require payment for mod-related content</li>
        <li>Bartering or trading goods/services that have monetary value</li>
        <li>Withholding features, updates, or support based on donation status</li>
    </ul>
    <p><strong>Permitted Donation Links:</strong></p>
    <p>Voluntary donation links are permitted with the following strict requirements:</p>
    <ul>
        <li>Donations must be completely optional with no impact on content access or functionality</li>
        <li>All mod features, downloads, and updates must remain equally available to all users regardless of donation status</li>
        <li>Donation links must not be prominently featured or aggressively promoted</li>
        <li>No exclusive content, early access, or special privileges may be offered to donors</li>
        <li>Donation links must not use manipulative or coercive language</li>
    </ul>
    <p><strong>Community Standards:</strong></p>
    <p>This policy ensures equal access to all community members regardless of financial means and maintains the collaborative spirit of the modding community. Content creators who include donation links must understand that all users, whether they donate or not, deserve the same level of access, support, and respect.</p>
    <p><strong>Consequences for Violations:</strong></p>
    <p>Any attempt to circumvent this policy through coded language, indirect benefits for donors, or creating a two-tier system based on financial contributions will result in immediate content removal and potential account termination. This includes subtle discrimination against non-donors or preferential treatment for those who contribute financially.</p>

    <h2 id="file-hosting">8. File Hosting and Distribution</h2>

    <h3>8.1 Download Link Requirements</h3>
    <p><strong>Direct Download Links (DDL) Required:</strong></p>
    <p>All download links must be direct download links that immediately begin downloading the file when visited. This requirement ensures the best user experience and enables automated tooling to download mods without user interaction.</p>
    <ul>
        <li>Links must initiate the download immediately</li>
        <li>The download link must confirm that the file is a 7-zip archive (.7z) as required by our archive standards</li>
        <li>Links must remain accessible indefinitely to ensure long-term availability</li>
    </ul>
    <p><strong>Prohibited Link Types:</strong></p>
    <p>Any download link that does not meet the direct download requirement is prohibited. This includes but is not limited to file sharing services with landing pages, ad-supported download sites, services requiring user interaction, temporary file sharing platforms, and any link that redirects users through multiple pages before downloading.</p>
    <p><strong>Recommended Hosting:</strong></p>
    <p>GitHub releases provide reliable direct download links with proper version control integration and meet all requirements for direct downloads of 7-zip archives.</p>

    <h2 id="violation-consequences">9. Violation Consequences and Appeals</h2>

    <h3>9.1 Guideline Violations</h3>
    <p><strong>Minor Violations:</strong></p>
    <p>Minor violations typically result from oversight or misunderstanding rather than malicious intent and can usually be corrected through collaboration with content creators.</p>
    <ul>
        <li>Missing documentation or improper formatting issues can usually be resolved quickly</li>
        <li>Incomplete version information represents administrative oversights rather than fundamental problems</li>
        <li>Minor licensing issues often stem from misunderstanding rather than intentional violation</li>
    </ul>
    <p><strong>Consequences:</strong> Requests for corrections with guidance, temporary content hiding until issues are resolved</p>
    <p><strong>Major Violations:</strong></p>
    <p>Major violations present significant risks to user safety or legal compliance and require immediate intervention to protect the community.</p>
    <ul>
        <li>Security risks or malicious code present immediate threats to user systems</li>
        <li>Copyright infringement creates legal liability for both creators and the platform</li>
        <li>Prohibited content types violate fundamental community standards</li>
    </ul>
    <p><strong>Consequences:</strong> Immediate content removal, account restrictions proportional to violation severity, possible permanent ban for egregious violations</p>

    <h3>9.2 Appeals Process</h3>
    <p><strong>Content Removal Appeals:</strong></p>
    <p>The appeals process provides creators with opportunities to address violations while maintaining platform security and compliance standards.</p>
    <ol>
        <li>Contact singleplayertarkov@gmail.com with specific details about the content and circumstances</li>
        <li>Provide evidence demonstrating that content complies with current guidelines</li>
        <li>Staff review is completed within 10 business days of receiving complete appeal information</li>
        <li>Decision is communicated with clear reasoning explaining the outcome</li>
    </ol>
    <p><strong>Improvement Opportunities:</strong></p>
    <ul>
        <li>Guidance is provided for bringing content into compliance with current standards</li>
        <li>Re-submission is allowed after corrections are made and verified</li>
        <li>Educational resources are provided to help creators avoid common issues in future submissions</li>
    </ul>

    <hr>

    <h2>Content Guidelines Summary</h2>
    <p><strong>Essential Requirements:</strong></p>
    <ul>
        <li>Proper packaging in standard archive formats with complete file structures</li>
        <li>Complete documentation including installation and usage instructions for all user skill levels</li>
        <li>Semantic versioning following MAJOR.MINOR.PATCH format with appropriate constraints</li>
        <li>Source code availability for all executable content to enable security review</li>
        <li>Security verification through VirusTotal scanning and code review processes</li>
    </ul>
    <p><strong>Quality Standards:</strong></p>
    <ul>
        <li>Functional testing before submission to ensure advertised features work correctly</li>
        <li>Performance optimization to avoid degrading user experience or system stability</li>
        <li>Clear licensing and proper attribution for all components and dependencies</li>
        <li>Professional presentation with comprehensive documentation and user guidance</li>
    </ul>
    <p><strong>Prohibited Content:</strong></p>
    <ul>
        <li>Security risks including malware, obfuscated code, or unauthorized system modifications</li>
        <li>Cheating tools that could work in multiplayer environments or resemble traditional hacks</li>
        <li>Adult content including nudity, sexual themes, or inappropriate material</li>
        <li>Copyright violations or unauthorized use of others' work without proper permission</li>
    </ul>
    <p><strong>Remember:</strong> These guidelines ensure The Forge maintains high standards for content quality, security, and user experience. When in doubt, contact staff for guidance before submitting.</p>
    <p><strong>Questions?</strong> Contact us at singleplayertarkov@gmail.com</p>

    <hr>

    <p><em>These Content Guidelines work together with our <a href="{{ route('static.terms') }}">Terms of Service</a>, <a href="{{ route('static.privacy') }}">Privacy Policy</a>, <a href="{{ route('static.community-standards') }}">Community Standards</a>, and <a href="{{ route('static.dmca') }}">DMCA Copyright Notice</a> to govern content on The Forge.</em></p>

    <p><em>Last updated: August 26, 2025</em></p>
</x-layouts.static-toc>
