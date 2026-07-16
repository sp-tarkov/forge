using Verifier.Inspection;

namespace Verifier.Checks;

/// <summary>Shared helpers for the DLL metadata checks' data payloads and GUID comparison.</summary>
public static class DllCheckData
{
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
