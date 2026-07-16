using Verifier.Inspection;
using Verifier.Output;

namespace Verifier.Checks;

/// <summary>
/// Verifies the GUIDs declared inside the archive's client and server mod DLLs against the GUID registered on the
/// Forge. Every component kind present must declare the registered GUID on at least one DLL; extra non-matching or
/// unreadable DLLs are reported in the data payload without failing. Skips when the verification target carries no
/// GUID, which is only the case for addon versions.
/// </summary>
public sealed class DllGuidMatchCheck : IVerificationCheck
{
    public string Name => "dll_guid_match";

    public CheckResult Run(CheckContext context)
    {
        DllScanSummary scan = context.DllScan;
        string expectedGuid = context.ModGuid.Trim();
        Dictionary<string, object?> data = BuildData(scan, expectedGuid);

        if (expectedGuid.Length == 0)
        {
            return CheckResult.Skipped(Name, "No GUID is registered on the Forge to compare against.", data: data);
        }

        if (scan.Findings.Count == 0)
        {
            return CheckResult.Skipped(Name, "The archive contains no client plugin or server mod metadata.", data: data);
        }

        List<string> failures = [];
        AddForgeMismatchFailure(failures, scan.Findings.Where(finding => finding.Kind == DllComponentKind.Client).ToList(), "client plugin", expectedGuid);
        AddForgeMismatchFailure(failures, scan.Findings.Where(finding => finding.Kind == DllComponentKind.Server).ToList(), "server mod", expectedGuid);

        return failures.Count > 0
            ? CheckResult.Failed(Name, string.Join(" ", failures), data: data)
            : CheckResult.Passed(Name, data: data);
    }

    /// <summary>Records a failure when a component kind has findings but none declares the mod's Forge GUID.</summary>
    private static void AddForgeMismatchFailure(List<string> failures, List<DllFinding> findings, string kindLabel, string expectedGuid)
    {
        if (findings.Count == 0 || findings.Any(finding => DllCheckData.GuidMatches(finding.Guid, expectedGuid)))
        {
            return;
        }

        string found = DistinctGuids(findings);
        failures.Add(found.Length > 0
            ? $"No {kindLabel} declares the mod's GUID ({expectedGuid}); found: {found}."
            : $"No {kindLabel} declares the mod's GUID ({expectedGuid}).");
    }

    /// <summary>Lists the distinct readable GUIDs of a set of findings for a failure message.</summary>
    private static string DistinctGuids(List<DllFinding> findings)
    {
        return string.Join(", ", findings.Select(finding => finding.Guid).OfType<string>().Distinct(StringComparer.OrdinalIgnoreCase));
    }

    /// <summary>Builds the check's data payload: the expected GUID and every finding with its match state.</summary>
    private static Dictionary<string, object?> BuildData(DllScanSummary scan, string expectedGuid)
    {
        List<Dictionary<string, object?>> findings = scan.Findings.Select(finding =>
        {
            Dictionary<string, object?> entry = DllCheckData.Finding(finding);
            entry["guid_matched"] = expectedGuid.Length > 0 && finding.Guid is not null
                ? DllCheckData.GuidMatches(finding.Guid, expectedGuid)
                : null;

            return entry;
        }).ToList();

        Dictionary<string, object?> data = DllCheckData.Summary(scan, findings);
        data["expected_guid"] = expectedGuid.Length > 0 ? expectedGuid : null;

        return data;
    }
}
