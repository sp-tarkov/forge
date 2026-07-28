using Verifier.Inspection;
using Verifier.Output;

namespace Verifier.Checks;

/// <summary>
/// Verifies every client plugin sits inside the BepInEx/plugins directory (or a subdirectory of it) at the archive
/// root, and that no plugin folder directly under BepInEx/plugins carries a semantic version in its name. Path
/// comparison is case-insensitive. Skips when the archive contains no client plugins.
/// </summary>
public sealed class ClientPluginLocationCheck : IVerificationCheck
{
    private const string ExpectedLocation = "BepInEx/plugins";

    private const int MaxPathsInMessage = 5;

    public string Name => "client_plugin_location";

    public CheckResult Run(CheckContext context)
    {
        DllScanSummary scan = context.DllScan;
        List<DllFinding> clientFindings = scan.Findings.Where(finding => finding.Kind == DllComponentKind.Client).ToList();
        List<string> misplaced = clientFindings
            .Select(finding => finding.Path)
            .Where(path => !path.StartsWith($"{ExpectedLocation}/", StringComparison.OrdinalIgnoreCase))
            .ToList();
        List<string> versionedFolders = DllCheckData.VersionedFolders(clientFindings, [ExpectedLocation]);
        Dictionary<string, object?> data = DllCheckData.LocationData(scan, clientFindings, ExpectedLocation, misplaced, versionedFolders);

        if (clientFindings.Count == 0)
        {
            return CheckResult.Skipped(Name, "The archive contains no client plugins.", data: data);
        }

        List<string> failures = [];

        if (misplaced.Count > 0)
        {
            failures.Add($"Client plugins must sit inside {ExpectedLocation} at the archive root; found outside it: {DllCheckData.JoinPaths(misplaced, MaxPathsInMessage)}.");
        }

        if (versionedFolders.Count > 0)
        {
            failures.Add($"Client plugin folders must not include a version number in their name; found: {DllCheckData.JoinPaths(versionedFolders, MaxPathsInMessage)}.");
        }

        return failures.Count > 0
            ? CheckResult.Failed(Name, string.Join(" ", failures), data: data)
            : CheckResult.Passed(Name, data: data);
    }
}
