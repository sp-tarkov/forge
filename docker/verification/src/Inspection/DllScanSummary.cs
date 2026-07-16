namespace Verifier.Inspection;

/// <summary>
/// The outcome of scanning the archive's DLLs: every mod component found plus the coverage counters the checks report.
/// </summary>
public sealed record DllScanSummary(
    List<DllFinding> Findings,
    int DllsScanned,
    int DllsSkippedBySize,
    bool Truncated);
