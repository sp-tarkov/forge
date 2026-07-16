namespace Verifier.Output;

/// <summary>The versioned container output contract consumed by the host.</summary>
public sealed record VerificationOutput(
    int SchemaVersion,
    string ChecksVersion,
    string? Sha256,
    ArchiveInfo? Archive,
    List<CheckResult> Checks,
    string? Error);
