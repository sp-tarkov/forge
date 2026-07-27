using System.Text.RegularExpressions;
using Verifier.Inspection;

namespace Verifier.Checks;

/// <summary>Shared helpers for the DLL checks' data payloads, failure messages, and GUID comparison.</summary>
public static class DllCheckData
{
    /// <summary>Joins up to <paramref name="maxPaths"/> paths, noting how many were omitted.</summary>
    public static string JoinPaths(List<string> paths, int maxPaths)
    {
        string joined = string.Join(", ", paths.Take(maxPaths));
        int omitted = paths.Count - maxPaths;

        return omitted > 0 ? $"{joined} and {omitted} more" : joined;
    }

    /// <summary>Matches a major.minor.patch version number within a folder name.</summary>
    private static readonly Regex SemanticVersionPattern = new(@"\d+\.\d+\.\d+", RegexOptions.Compiled);

    /// <summary>
    /// Builds the location checks' payload: scan counters, expected location, misplaced paths, and versioned folders.
    /// </summary>
    public static Dictionary<string, object?> LocationData(DllScanSummary scan, List<DllFinding> findings, string expectedLocation, List<string> misplacedPaths, List<string> versionedFolders)
    {
        Dictionary<string, object?> data = Summary(scan, findings.Select(Finding).ToList());
        data["expected_location"] = expectedLocation;
        data["misplaced_paths"] = misplacedPaths;
        data["versioned_folders"] = versionedFolders;

        return data;
    }

    /// <summary>Selects distinct folder names under the roots that carry a semantic version in their name.</summary>
    public static List<string> VersionedFolders(List<DllFinding> findings, List<string> roots)
    {
        return findings
            .Select(finding => FolderUnderRoot(finding.Path, roots))
            .OfType<string>()
            .Where(folder => SemanticVersionPattern.IsMatch(folder))
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .ToList();
    }

    /// <summary>Extracts the first path segment below a matching root, or null when the path sits at a root.</summary>
    private static string? FolderUnderRoot(string path, List<string> roots)
    {
        foreach (string root in roots)
        {
            string prefix = $"{root}/";

            if (!path.StartsWith(prefix, StringComparison.OrdinalIgnoreCase))
            {
                continue;
            }

            int separator = path.IndexOf('/', prefix.Length);

            if (separator > prefix.Length)
            {
                return path[prefix.Length..separator];
            }
        }

        return null;
    }

    /// <summary>Determines whether a finding's GUID equals the expected GUID, ignoring case.</summary>
    public static bool GuidMatches(string? guid, string expectedGuid)
    {
        return guid is not null && guid.Equals(expectedGuid, StringComparison.OrdinalIgnoreCase);
    }

    /// <summary>Builds the payload keys shared by both checks: findings plus the scan coverage counters.</summary>
    public static Dictionary<string, object?> Summary(DllScanSummary scan, List<Dictionary<string, object?>> findings)
    {
        return new Dictionary<string, object?>
        {
            ["findings"] = findings,
            ["dlls_scanned"] = scan.DllsScanned,
            ["dlls_skipped_by_size"] = scan.DllsSkippedBySize,
            ["findings_truncated"] = scan.Truncated,
        };
    }

    /// <summary>Builds the payload entry for a single finding.</summary>
    public static Dictionary<string, object?> Finding(DllFinding finding)
    {
        return new Dictionary<string, object?>
        {
            ["path"] = finding.Path,
            ["kind"] = finding.Kind == DllComponentKind.Client ? "client" : "server",
            ["guid"] = finding.Guid,
            ["name"] = finding.Name,
            ["version"] = finding.Version,
        };
    }
}
