<x-layouts.static-toc>
    <x-slot name="pageTitle">{{ __('File Submission Guidelines') }}</x-slot>

    <x-slot name="pageDescription">{{ __('File Submission Guidelines for SPT') }}</x-slot>

    <x-slot name="tableOfContents">
        <x-table-of-contents-item href="#usage-accreditation" title="Usage and Accreditation" />
        <x-table-of-contents-item href="#what-is-cheat" title='What is considered a "cheat"?'>
            <x-table-of-contents-subitem href="#allowed-mods">Allowed mods</x-table-of-contents-subitem>
            <x-table-of-contents-subitem href="#forbidden-mods">Forbidden mods</x-table-of-contents-subitem>
        </x-table-of-contents-item>
        <x-table-of-contents-item href="#file-hosting" title="File hosting guidelines" />
        <x-table-of-contents-item href="#inappropriate-content" title="Inappropriate Content">
            <x-table-of-contents-subitem href="#inappropriate-content-glance">Guidelines at a glance</x-table-of-contents-subitem>
        </x-table-of-contents-item>
        <x-table-of-contents-item href="#child-characters" title="Content related to Child Characters">
            <x-table-of-contents-subitem href="#defining-child-characters">Defining "Child Characters"</x-table-of-contents-subitem>
            <x-table-of-contents-subitem href="#child-mods-permissible">What is permissible</x-table-of-contents-subitem>
            <x-table-of-contents-subitem href="#responsible-conduct">Responsible conduct</x-table-of-contents-subitem>
        </x-table-of-contents-item>
        <x-table-of-contents-item href="#associated-content" title="Associated Content and Categorization" />
        <x-table-of-contents-item href="#distribution-permissions-users" title="Distribution Permissions (Users)" />
        <x-table-of-contents-item href="#distribution-permissions-spt" title="Distribution Permissions (SPT)" />
    </x-slot>

    <p>In order to maintain a healthy environment that promotes both collaboration and accountability among our community, the following guidelines describe the acceptable use of SPT file, image, and video sharing systems.</p>
    <p>Submitted files (mods, images, etc.) and their associated content (names, descriptions, etc.) must adhere to the standards described in this document. Submitted files and any associated content may be reviewed by staff and may be subject to administrative action.</p>

    <h2 id="usage-accreditation">Usage and Accreditation</h2>
    <p>User submissions which include copyrighted content, be it game files or images, are prohibited unless proper permission has been granted by the property owner or appropriate representation. Such content may be removed from SPT websites and services and the infringing user account might be subject to administrative action.</p>
    <p>Submissions must conform to the following guidelines:</p>
    <ul>
        <li>Submission of existing user-submitted content without obtaining permission from the original author(s) of said content is strictly prohibited.</li>
        <li>Proper credit to the original author(s) must be given unless the author(s) has/have specified that credit is not necessary. Please, be aware that the mere accreditation of an author is no substitute for receiving explicit permission to upload or modify someone else's content.</li>
        <li>The uploader is responsible for understanding the legalities involved when using copyrighted work and submissions that contain such content may be removed.</li>
        <li>Compilations must provide functionality beyond repackaging. In other words, an acceptable compilation must be "more than the sum of its parts".</li>
        <li>All files submitted must be intended for sharing publicly. You must not use our services for your own personal storage or share files with a limited group of users.</li>
        <li>Language translations should be submitted only as an addition that works in conjunction with the original file and you should link to the original file as a mirror or in your file description. Translations that include content from the original mod without permission may be subject to moderation and will be removed if requested by the original file author(s).</li>
        <li>User-submitted content that is predominantly intended to interact with existing user-submitted content is subject to the approval of all parties involved and may be removed at the request of the author of the original content.</li>
        <li>If your mod contains .dll or .exe files:
            <ul>
                <li>The file should not be obfuscated in any way.</li>
                <li>The file code should be open-source and recent. A link to a publicly accessible repository must be provided.</li>
                <li>A VirusTotal link need to be provided and have 0 detections. False positives are only accepted if there are 1 or 2 detections and are allowed on a per-mod basis.</li>
                <li>The VirusTotal link must be updated for every new release. The source code link must contain the latest release's code and be accessible publicly.</li>
            </ul>
        </li>
        <li>Logging should be utilized to provide pertinent information to the end user, and log output should not be excessive or otherwise impede the end users ability to be notified of errors or warnings. This includes both logging output to the console, as well as to log files. The following is a non-exhaustive list of restricted uses of the logging functionality:
            <ul>
                <li>Any sort of ASCII or Unicode art or logo</li>
                <li>Multi-line mod author or team credits</li>
                <li>Any advertising of external links</li>
            </ul>
        </li>
    </ul>
    <p>We encourage all copyright holders to report unauthorized use of their property by utilizing the "report" function on a given file's page.</p>

    <h2 id="what-is-cheat">What is considered a "cheat"?</h2>
    <p>To reduce the amount of confusion and disputes between mod authors and staff, these guidelines have been created to distinguish what types of mods and tools are okay to upload and which ones break Rule #5.</p>
    <h3 id="allowed-mods">Allowed mods:</h3>
    <ul>
        <li>Old-school type cheats - God Mode, Infinite Ammo, Noclip, etc.</li>
        <li>Debug tools, menus and external programs that are made with the express intent to help with development or testing of other mods (ex. BotMonitor)</li>
    </ul>
    <h3 id="forbidden-mods">Forbidden mods:</h3>
    <ul>
        <li>Client-side mods or external programs that could be used on the Live version of EFT in any way without retooling the mod to do so. This excludes programs that are too generic, like dnSpy.</li>
        <li>Mods that look like "hacks" in the traditional sense. Basically, if taken out of context, the mod shouldn't look like it's just hacks made for EFT (ex. ESP, wallhack, aimbot, etc.)</li>
    </ul>
    <p>The purpose of these rules is to prevent SPT from being associated with cheats and cheat development to people that are not familiar with the project.</p>
    <p>These guidelines are not final and all the judgements are entirely subjective. Staff retain the ability to update and change these guidelines when new cases and disputes arise. If you're not sure if your tool or mod would be allowed - reach out to Terkoiz on the SPT Pub Discord server.</p>

    <h2 id="file-hosting">File hosting guidelines</h2>
    <ul>
        <li>All download links must be functional and publicly-accessible. Older version download links need to remain accessible, otherwise the individual inaccessible versions should be disabled/deleted.
            <ul>
                <li>Rate-limited links (e.g. Google Drive's "download quota limit exceeded" error) are an exception and will not be subject to moderation, as these are temporary issues.</li>
            </ul>
        </li>
        <li>Links to websites which promise monetary rewards to the uploader based on downloads are strictly prohibited (e.g. ModsFire, ShareMods)</li>
        <li>Links to websites which have obnoxious and overwhelming ads are prohibited.</li>
    </ul>

    <h2 id="inappropriate-content">Inappropriate Content</h2>
    <p>As we serve a diverse audience, user-submitted content needs to be suitable for all audiences.</p>
    <p>Please note that there are particular rules in place regarding the public image share. Nudity is not acceptable in the regular image share.</p>
    <h3 id="inappropriate-content-glance">Inappropriate Content Guidelines at a glance:</h3>
    <ul>
        <li>Images, files or videos that contain nudity are strictly forbidden to be shared on SPT services or websites.</li>
        <li>Content that may be generally construed as provocative, divisive, objectionable, discriminatory, or abusive toward any real-world individual or group, may be subject to moderation. This includes but is not limited to content involving politics, race, religion, gender identity, sexuality, or social class.</li>
        <li>Content related to the World War II Axis Powers (Nazi Germany, Imperial Japan, Fascist Italy) and contemporary groups sharing similar ideology.</li>
        <li>Content that enables or promotes illegal activity, including but not limited to software piracy and copyright infringement, may be subject to moderation. Submissions must adhere to the licences and agreements associated with the modified or featured title(s).</li>
        <li>Files that are intended to destroy or otherwise cause harm to a user's person or property, such as viruses, malware, adware, etc., are prohibited. Submitted files will be subject to virus scanning and may be moderated as a result.</li>
        <li>Files that may be disruptive to any persistent multiplayer environment are prohibited.</li>
        <li>Files must be presented in good faith and in a functional state with respect to any caveats or requirements that you must detail and present to the user. Files that require registration or submission of user information in order to function, may only do so to facilitate the functions associated with the mod and must be clearly presented as such to the user.</li>
        <li>Files (especially executables) that connect to the internet to download or send information and/or files are prohibited unless where it is crucial for the functioning of the mod/utility. Please note that "auto update" functionality does not qualify as crucial and that we reserve the right to moderate and/or remove any files/tools/utilities/mods that communicate with the internet. If your tool's/app's functionality depends on the ability to send and receive information/files, please contact staff or send an message that pings @staff on Discord in the #website-general channel laying out your reasoning and providing the source code for your tool/app.</li>
        <li>Placeholders, password-protected, or otherwise wholly non-functioning files are prohibited.</li>
        <li>Using either files or images as a means of harassment of third parties is strictly prohibited.</li>
        <li>File, image, and video submissions that feature content that would not adhere to the guidelines above on SPT are prohibited.</li>
        <li>Files may not replace existing EFT or SPT data. File overwriting or other forms of destructive edits are forbidden. All files must edit values at launch using the mod loader.
            <ul>
                <li>This rule exists to ensure that mods can easily be uninstalled by simply removing the relevant mod folder or plugin file. Therefore, mods that use the mod loader to permanently edit asset or database files are also prohibited, unless explicitly allowed by Staff.</li>
            </ul>
        </li>
    </ul>

    <h2 id="child-characters">Content related to Child Characters</h2>
    <p>It is one of our core beliefs that modding can be a boon to your game experience and we believe that there is a place for mods improving upon the visuals of child characters in a decent and respectful manner.</p>
    <p>That being said, we understand that this is a sensitive matter that calls for clear guidelines as to what is and is not permissible when it comes to altering the appearance of child characters.</p>
    <h3 id="defining-child-characters">Defining "Child Characters"</h3>
    <p>A "child or child-like" character or NPC is defined as any character whose physical appearance or general characteristics gives sufficient reason to assume that they are a minor as defined by French law (under the age of 18). We identify this type of character with the following guidelines:</p>
    <ul>
        <li>Featuring bodily proportions that are predominantly associated with children. (examples include; small bodies, large heads and eyes, etc.)</li>
        <li>Having physical similarities to a child in height, build, features or mannerisms.</li>
        <li>Presented in a way that is indicative of a child in the base game.</li>
        <li>Canonically a child or child-like character in relation to the base game.</li>
        <li>Would be considered a child without game-specific context/lore defining the character (e.g. a childlike vampire established to be millennia old).</li>
    </ul>
    <p>Some examples of characters fitting these definitions:</p>
    <ul>
        <li>A regular child character. (e.g. child Link in Legend of Zelda: Ocarina of Time)</li>
        <li>A character who has had their physical characteristics frozen in time, but is described to be a lot older than they physically appear. (e.g. Babette in Skyrim, 200-year-old vampire in a child body).</li>
        <li>An older character who has been "de-aged" or had their consciousness transferred into a younger, child-like body.</li>
        <li>A character who appears to be an adult but is described and established as a child.</li>
        <li>A character whose listed age is that of an adult but their physical body resembles a child.</li>
        <li>A robot, android or other non-human, but anthropomorphised character with physical features indicative of human children (e.g. 10 year old Shaun from Fallout 4).</li>
    </ul>
    <h3 id="child-mods-permissible">What is and is not permissible when it comes to child mods?</h3>
    <h4 id="vanilla-reference">Reference point - vanilla game:</h4>
    <p>Often times the developers of a given game have set the standards themselves by deliberately taking measures to ensure child characters are not able to be sexualized. This may include but is often not limited to:</p>
    <ul>
        <li>Irremovable clothes</li>
        <li>Irremovable underwear</li>
        <li>Underwear that is part of a given body mesh, or body texture (rather than an in-game item)</li>
        <li>Deliberate omission of reproductive organs or other sexual features ("Barbie/Ken" bodies)</li>
        <li>The inability for child characters to partake in any "romance" quest, plotline, or otherwise activity that can be perceived as romantic or sexual</li>
    </ul>
    <p>For the purpose of mods altering the appearance of child characters, it is, therefore, required that mod authors refrain from doing anything that would allow for measures such as those outlined above to be circumvented in one way or another:</p>
    <ul>
        <li>Clothes must remain irremovable through regular, in-game means</li>
        <li>Underwear must remain irremovable in the same way it would not be easily removable in the vanilla game without impacting the integrity of the body mesh/model and/or texture</li>
        <li>Any mod added child body or alteration must not feature reproductive organs or other sexual features (breasts)</li>
        <li>Any mod added child body model must not enhance sexual characteristics in a way that is typically associated with adult bodies</li>
        <li>Child characters must not be made available for "romance" or "marriage" quests or similar</li>
        <li>Child characters must not be given abilities or functionality that can be perceived as romantic or sexual</li>
    </ul>
    <p>Likewise, modded clothing attire for children should use the vanilla game as a reference point when it comes to determining the level of modesty that is appropriate in the given game context.</p>
    <ul>
        <li>Clothes for children should, in general, not be more revealing than would be considered appropriate for a child in a comparable real-world scenario</li>
        <li>Underwear (i.e. clothes that would be shown underneath a given default outfit) must not be substantially more revealing than the base game underwear to a degree that would imply sexualization</li>
    </ul>
    <p>Note that while e.g. three-quarter trousers are technically more revealing than e.g. a turtleneck shirt in combination with a ball gown, it would still be considered modest on a child in a real-world scenario and would, therefore, be in compliance with our guidelines.</p>
    <h3 id="responsible-conduct">Responsible conduct surrounding child character modifications</h3>
    <p>Given the at times sensitive nature of the topic at hand, it is of paramount importance to remain respectful and tactful when discussing modifications of child characters and associated content:</p>
    <ul>
        <li>Mods featuring child characters should be appropriately named and refrain from using ambiguous attribution implying sexualization or sexual connotation including but not limited to adjectives such as "sassy", "naughty", "sexy", "(barely-)legal", etc.</li>
        <li>Mod descriptions and associated content must not contain allusions to paraphiliae associated with illegal activity, nor hint at or openly discuss the (il)legality of child exploitation, grooming, etc.</li>
        <li>Discussing, hinting at, linking, or soliciting content that might be used in combination with a given child character mod in order to sexualize the child characters (e.g. external tools to add breast/butt physics) is strictly prohibited.</li>
    </ul>
    <p>At all times, we reserve the right to decide what we believe is and is not acceptable conduct surrounding the sensitive matter of child character modifications.</p>

    <h2 id="associated-content">Associated Content and Categorization</h2>
    <p>The following describes our guidelines when it comes to so-called Associated Content i.e. file names and descriptions:</p>
    <ul>
        <li>Files must be properly categorized.</li>
        <li>Files must be packaged in a standard archive format (.zip/.rar/.7z)</li>
        <li>Files must contain executable code or scripts. Any uploads that only consist of loose data files (audio, images) that require some sort of external dependency to function must be contained within a thread relevant to the dependency.
            <ul>
                <li>For client mods, functioning BepInEx plugin files must be provided</li>
                <li>For tools, some sort of executable files must be provided (.exe, .cmd, .bat, etc.)</li>
                <li>For server mods, properly packaged JS/TS files must be provided along with the related package.json file</li>
                <li>Configuration preset files are exempt from this rule, provided the edits are plentiful and warrant a standalone upload</li>
            </ul>
        </li>
        <li>Files must be appropriately tagged according to the file's content. Abuse of the tagging system and the addition of irrelevant tags is prohibited.</li>
        <li>File descriptions must be legible and informative regarding the nature of the file. Wholly misleading or incohesive descriptions are prohibited.</li>
        <li>Providing links to external sources for files that would not be permissible to be hosted on our services due to copyright violations or other is prohibited.</li>
        <li>When uploading images for files make sure they are relevant to the mod/game, don't mislead people and ensure no adult-only images are uploaded.</li>
        <li>Public placeholders are prohibited. File pages without functioning files should remain unpublished, hidden, or be removed.</li>
    </ul>

    <h2 id="distribution-permissions-users">Distribution Permissions in Relation to other Users</h2>
    <p>We provide users with the ability to detail how they wish their work to be reused within the SPT network via our various permission preset options. Bear in mind that these permission settings are file specific. Alternatively, users are free to state their own terms regarding permissions for their content on a given file page. Only the original author of a given file or asset can specify its distribution permissions.</p>
    <p>If the author has not provided any permission information at all - you should assume that you must request permission from the author.</p>
    <p>Should any disputes occur within the context of our websites and services, these permissions will be referred to by staff who will attempt to enforce your wishes, within reason. If another SPT user does not adhere to posted permission statements, their account may be subject to moderation based on review.</p>
    <p>If you give your permission to another author for them and/or others to use your work, then, pending a breach of whatever terms you decided, you cannot take back that permission once the other user(s)'s file has been released.</p>
    <p>Permissions and provisions you granted to other users of our site with regard to your content will remain in effect even if you cease to use SPT or in the event that your account is terminated.</p>
    <p>Always document your conversations with other authors regarding permissions, preferably via private message on the SPT Workshop or on Discord, and provide staff with any pertinent evidence to facilitate investigation and conflict resolution when needed.</p>
    <h2 id="distribution-permissions-spt">Distribution Permissions in Relation to SPT</h2>
    <p>You only retain the rights to files you have uploaded to a SPT site. You do not own the rights to files or content not posted by yourself that might have been created as a result of your content being available on any SPT site, including, but not limited to, ratings, comments, images created by other users, articles in regards to your content and any statistical information in regards to your content.</p>
    <p>As a general rule, we do not remove user-submitted content from banned users, unless it is found in violation of our policies and guidelines. If your account is terminated by SPT staff, you are free to decide how you want us to handle your uploaded content going forward by e.g. requesting its removal via singleplayertarkov@gmail.com.</p>
</x-layouts.static-toc>
