using Verifier.Inspection;
using Verifier.Output;

namespace Verifier.Checks;

/// <summary>
/// Verifies every client mod sits inside the BepInEx/plugins or BepInEx/patchers directory (or a subdirectory of one)
/// at the archive root, and that no mod folder directly under either carries a semantic version in its name. Path
/// comparison is case-insensitive. Skips when the archive contains no client mods.
/// </summary>
public sealed class ClientPluginLocationCheck : IVerificationCheck
{
    private static readonly List<string> AllowedLocations = ["BepInEx/plugins", "BepInEx/patchers"];

    private const int MaxPathsInMessage = 5;

    public string Name => "client_plugin_location";

    public CheckResult Run(CheckContext context)
    {
        DllScanSummary scan = context.DllScan;
        List<DllFinding> clientFindings = scan.Findings.Where(finding => finding.Kind == DllComponentKind.Client).ToList();
        string expectedLocation = string.Join(" or ", AllowedLocations);
        List<string> misplaced = clientFindings
            .Select(finding => finding.Path)
            .Where(path => !AllowedLocations.Any(location => path.StartsWith($"{location}/", StringComparison.OrdinalIgnoreCase)))
            .ToList();
        List<string> versionedFolders = DllCheckData.VersionedFolders(clientFindings, AllowedLocations);
        Dictionary<string, object?> data = DllCheckData.LocationData(scan, clientFindings, expectedLocation, misplaced, versionedFolders);

        if (clientFindings.Count == 0)
        {
            return CheckResult.Skipped(Name, "The archive contains no client mods.", data: data);
        }

        List<string> failures = [];

        if (misplaced.Count > 0)
        {
            failures.Add($"Client mods must sit inside {expectedLocation} at the archive root; found outside them: {DllCheckData.JoinPaths(misplaced, MaxPathsInMessage)}.");
        }

        if (versionedFolders.Count > 0)
        {
            failures.Add($"Client mod folders must not include a version number in their name; found: {DllCheckData.JoinPaths(versionedFolders, MaxPathsInMessage)}.");
        }

        return failures.Count > 0
            ? CheckResult.Failed(Name, string.Join(" ", failures), data: data)
            : CheckResult.Passed(Name, data: data);
    }
}
