namespace Verifier.Output;

/// <summary>
/// The outcome of a single check. A report-only check is recorded but does not fail the verification.
/// </summary>
public sealed record CheckResult(
    string Name,
    string Status,
    bool ReportOnly,
    string? Message,
    Dictionary<string, object?> Data)
{
    /// <summary>Builds a passing check.</summary>
    public static CheckResult Passed(string name, bool reportOnly = false, Dictionary<string, object?>? data = null)
    {
        return new CheckResult(name, "passed", reportOnly, null, data ?? new Dictionary<string, object?>());
    }

    /// <summary>Builds a failing check.</summary>
    public static CheckResult Failed(string name, string message, bool reportOnly = false, Dictionary<string, object?>? data = null)
    {
        return new CheckResult(name, "failed", reportOnly, message, data ?? new Dictionary<string, object?>());
    }

    /// <summary>Builds a skipped check.</summary>
    public static CheckResult Skipped(string name, string message, bool reportOnly = false, Dictionary<string, object?>? data = null)
    {
        return new CheckResult(name, "skipped", reportOnly, message, data ?? new Dictionary<string, object?>());
    }
}
