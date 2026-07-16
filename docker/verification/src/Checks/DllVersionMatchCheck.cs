using Verifier.Inspection;
using Verifier.Output;
using Version = SemanticVersioning.Version;

namespace Verifier.Checks;

/// <summary>
/// Verifies the version numbers declared inside the archive's mod component DLLs against the version published on the
/// Forge. Only components declaring the mod's registered GUID are compared, and each component kind present must
/// declare the published version on at least one DLL; extra non-matching or unreadable DLLs are reported in the data
/// payload without failing. Versions compare by semantic version precedence, which ignores build metadata. Runs
/// independently of the GUID check, and skips when the verification target carries no GUID.
/// </summary>
public sealed class DllVersionMatchCheck : IVerificationCheck
{
    public string Name => "dll_version_match";

    public CheckResult Run(CheckContext context)
    {
        DllScanSummary scan = context.DllScan;
        string expectedGuid = context.ModGuid.Trim();
        string expectedRaw = context.ModVersion.Trim();
        Version? expected = TryParse(expectedRaw);
        Dictionary<string, object?> data = BuildData(scan, expectedGuid, expectedRaw, expected);

        if (expectedGuid.Length == 0)
        {
            return CheckResult.Skipped(Name, "No GUID is registered on the Forge to compare against.", data: data);
        }

        if (scan.Findings.Count == 0)
        {
            return CheckResult.Skipped(Name, "The archive contains no client plugin or server mod metadata.", data: data);
        }

        if (expected is null)
        {
            string message = expectedRaw.Length == 0
                ? "No published version number was provided to compare against."
                : $"The published version number ({expectedRaw}) is not a valid semantic version.";

            return CheckResult.Skipped(Name, message, data: data);
        }

        List<DllFinding> comparison = ComparisonSet(scan, expectedGuid);

        if (comparison.Count == 0)
        {
            return CheckResult.Failed(
                Name,
                $"No DLL in the archive declares the mod's GUID ({expectedGuid}), so the published version ({expectedRaw}) could not be verified.",
                data: data);
        }

        List<string> failures = [];
        AddKindMismatchFailure(failures, comparison.Where(finding => finding.Kind == DllComponentKind.Client).ToList(), "client plugin", expected, expectedRaw);
        AddKindMismatchFailure(failures, comparison.Where(finding => finding.Kind == DllComponentKind.Server).ToList(), "server mod", expected, expectedRaw);

        return failures.Count > 0
            ? CheckResult.Failed(Name, string.Join(" ", failures), data: data)
            : CheckResult.Passed(Name, data: data);
    }

    /// <summary>
    /// Records a failure when a component kind is present in the comparison set but none of its DLLs declares the
    /// published version.
    /// </summary>
    private static void AddKindMismatchFailure(List<string> failures, List<DllFinding> findings, string kindLabel, Version expected, string expectedRaw)
    {
        if (findings.Count == 0 || findings.Any(finding => MatchesExpected(finding.Version, expected)))
        {
            return;
        }

        string found = string.Join(", ", findings.Select(finding => finding.Version).OfType<string>().Distinct(StringComparer.OrdinalIgnoreCase));
        failures.Add(found.Length > 0
            ? $"No {kindLabel} declares the published version ({expectedRaw}); found: {found}."
            : $"No {kindLabel} declares the published version ({expectedRaw}).");
    }

    /// <summary>Determines whether a version parses and is precedence-equal to the published version.</summary>
    private static bool MatchesExpected(string? version, Version expected)
    {
        return TryParse(version)?.CompareTo(expected) == 0;
    }

    /// <summary>Selects the findings declaring the mod's registered GUID.</summary>
    private static List<DllFinding> ComparisonSet(DllScanSummary scan, string expectedGuid)
    {
        return scan.Findings.Where(finding => DllCheckData.GuidMatches(finding.Guid, expectedGuid)).ToList();
    }

    /// <summary>Parses a semantic version, returning null when the string is missing or invalid.</summary>
    private static Version? TryParse(string? version)
    {
        return !string.IsNullOrWhiteSpace(version) && Version.TryParse(version, out Version? parsed) ? parsed : null;
    }

    /// <summary>
    /// Builds the check's data payload: the expected version and GUID, plus every finding with its match state.
    /// </summary>
    private static Dictionary<string, object?> BuildData(DllScanSummary scan, string expectedGuid, string expectedRaw, Version? expected)
    {
        HashSet<DllFinding> comparison = [.. ComparisonSet(scan, expectedGuid)];

        List<Dictionary<string, object?>> findings = scan.Findings.Select(finding =>
        {
            Dictionary<string, object?> entry = DllCheckData.Finding(finding);
            entry["guid_matched"] = expectedGuid.Length > 0 && finding.Guid is not null
                ? DllCheckData.GuidMatches(finding.Guid, expectedGuid)
                : null;
            entry["version_matched"] = expected is not null && comparison.Contains(finding)
                ? MatchesExpected(finding.Version, expected)
                : null;

            return entry;
        }).ToList();

        Dictionary<string, object?> data = DllCheckData.Summary(scan, findings);
        data["expected_version"] = expectedRaw.Length > 0 ? expectedRaw : null;
        data["mod_guid"] = expectedGuid.Length > 0 ? expectedGuid : null;

        return data;
    }
}
