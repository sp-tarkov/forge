using Verifier.Inspection;
using Verifier.Output;

namespace Verifier.Checks;

/// <summary>
/// Verifies every server mod DLL sits in its own folder under the user/mods directory of the SPT root matching the
/// mod's SPT generation at the archive root: SPT/user/mods for 4.0 mods and SPT_Runtime/user/mods for 4.1 and later,
/// and that no mod folder carries a semantic version in its name. A constraint spanning both generations fails
/// outright. Path comparison is case-insensitive. Skips when the archive contains no server mods.
/// </summary>
public sealed class ServerModLocationCheck : IVerificationCheck
{
    private const string Generation40 = "4.0";

    private const string Generation41 = "4.1";

    private const string GenerationMixed = "mixed";

    private const int MaxPathsInMessage = 5;

    public string Name => "server_mod_location";

    public CheckResult Run(CheckContext context)
    {
        DllScanSummary scan = context.DllScan;
        List<DllFinding> serverFindings = scan.Findings.Where(finding => finding.Kind == DllComponentKind.Server).ToList();
        List<string> allowedRoots = AllowedRoots(context.SptGeneration);
        string expectedLocation = string.Join(" or ", allowedRoots.Select(root => $"{root}/user/mods"));
        List<string> misplaced = serverFindings
            .Select(finding => finding.Path)
            .Where(path => !allowedRoots.Any(root => IsInOwnModFolder(path, root)))
            .ToList();
        List<string> versionedFolders = DllCheckData.VersionedFolders(serverFindings, allowedRoots.Select(root => $"{root}/user/mods").ToList());
        Dictionary<string, object?> data = DllCheckData.LocationData(scan, serverFindings, expectedLocation, misplaced, versionedFolders);
        data["spt_generation"] = context.SptGeneration;

        if (serverFindings.Count == 0)
        {
            return CheckResult.Skipped(Name, "The archive contains no server mods.", data: data);
        }

        if (context.SptGeneration == GenerationMixed)
        {
            return CheckResult.Failed(Name, "The mod version's SPT version constraint spans both 4.0 and 4.1, which are incompatible.", data: data);
        }

        List<string> failures = [];

        if (misplaced.Count > 0)
        {
            failures.Add($"Server mod files must sit in their own folder under {expectedLocation} at the archive root; found outside it: {DllCheckData.JoinPaths(misplaced, MaxPathsInMessage)}.");
        }

        if (versionedFolders.Count > 0)
        {
            failures.Add($"Server mod folders must not include a version number in their name; found: {DllCheckData.JoinPaths(versionedFolders, MaxPathsInMessage)}.");
        }

        return failures.Count > 0
            ? CheckResult.Failed(Name, string.Join(" ", failures), data: data)
            : CheckResult.Passed(Name, data: data);
    }

    /// <summary>The SPT root directories whose user/mods folder may hold the generation's server mods.</summary>
    private static List<string> AllowedRoots(string sptGeneration)
    {
        return sptGeneration switch
        {
            Generation40 => ["SPT"],
            Generation41 => ["SPT_Runtime"],
            _ => ["SPT", "SPT_Runtime"],
        };
    }

    /// <summary>Determines whether a path sits inside its own folder under the root's user/mods directory.</summary>
    private static bool IsInOwnModFolder(string path, string root)
    {
        string prefix = $"{root}/user/mods/";

        return path.StartsWith(prefix, StringComparison.OrdinalIgnoreCase) && path.IndexOf('/', prefix.Length) > prefix.Length;
    }
}
