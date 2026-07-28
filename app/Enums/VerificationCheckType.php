<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Identifies the checks a verification run can perform, providing the label and description for each. Most checks are
 * implemented in the docker/verification/src project and registered in Checks/CheckRegistry.cs.
 */
enum VerificationCheckType: string
{
    /**
     * The archive file was downloaded from the version's URL within the configured safety limits. This check runs on
     * the host side before the container checks and is "faked" into the container check list when it fails.
     */
    case FileDownload = 'file_download';

    /**
     * The archive was unpacked within the configured safety limits.
     */
    case ArchiveExtraction = 'archive_extraction';

    /**
     * The GUIDs declared inside the archive's client plugin and server mod DLLs match the GUID registered for the mod
     * on the Forge, and match each other (when both types of mods exist).
     */
    case DllGuidMatch = 'dll_guid_match';

    /**
     * The version numbers declared inside the archive's client plugin and server mod DLLs match the version number
     * published on the Forge.
     */
    case DllVersionMatch = 'dll_version_match';

    /**
     * Every client plugin in the archive sits inside the `BepInEx/plugins` directory (or a subdirectory of it) at the
     * archive root, and no plugin folder under `BepInEx/plugins` includes a version number in its name.
     */
    case ClientPluginLocation = 'client_plugin_location';

    /**
     * Every server mod in the archive sits in its own folder under the `<spt_data_path>/user/mods` directory. The
     * <spt_data_path> is `SPT` for 4.0 mods, and `SPT_Runtime` for 4.1 and later. Mod folders can not include a version
     * number in their name.
     */
    case ServerModLocation = 'server_mod_location';

    /**
     * Get the display label for a raw check name, humanizing unknown names.
     */
    public static function labelFor(string $name): string
    {
        return self::tryFrom($name)?->label() ?? Str::headline($name);
    }

    /**
     * Get the author-facing description for a raw check name, or null when the name is unknown.
     */
    public static function descriptionFor(string $name): ?string
    {
        return self::tryFrom($name)?->description();
    }

    /**
     * Get the human-readable label for the check.
     */
    public function label(): string
    {
        return match ($this) {
            self::FileDownload => 'File Download',
            self::ArchiveExtraction => 'Archive Extraction',
            self::DllGuidMatch => 'GUID Match',
            self::DllVersionMatch => 'Version Match',
            self::ClientPluginLocation => 'Client Plugin Location',
            self::ServerModLocation => 'Server Mod Location',
        };
    }

    /**
     * Get the author-facing description of what the check verifies.
     */
    public function description(): string
    {
        return match ($this) {
            self::FileDownload => 'Confirms the download URL serves the mod archive file directly. A failure usually means the link points to a web page instead of a file, requires a login, or the file has been removed.',
            self::ArchiveExtraction => 'Confirms the uploaded archive can be opened and its files unpacked safely. A failure usually means the file is corrupted, uses an unsupported format, or expands to an unreasonably large size.',
            self::DllGuidMatch => 'Confirms the GUID declared inside the client and server DLLs matches the GUID registered for the mod on the Forge, and that the client and server agree with each other. A failure usually means the DLLs were built with a different GUID than the one registered.',
            self::DllVersionMatch => 'Confirms the version numbers declared inside the client and server DLLs match the version number published on the Forge. A failure usually means the archive was built from a different version than the one published.',
            self::ClientPluginLocation => 'Confirms every client plugin sits inside the BepInEx/plugins directory at the root of the archive, so it loads when the archive is extracted into the game folder, and that plugin folders do not include a version number in their name. A failure usually means the plugin is nested under a wrapper folder, placed outside BepInEx/plugins, or sits in a folder named with a version number.',
            self::ServerModLocation => "Confirms every server mod sits in its own folder under SPT/user/mods for SPT 4.0 mods, or SPT_Runtime/user/mods for SPT 4.1 and later, at the root of the archive, and that the mod folder does not include a version number in its name. A failure usually means the mod is under the wrong root for the targeted SPT version, sits directly in user/mods without its own folder, sits in a folder named with a version number, or the mod versions's SPT version constraint spans both incompatible SPT versions.",
        };
    }
}
