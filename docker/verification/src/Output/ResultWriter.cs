using System.Text.Json;
using System.Text.Json.Serialization;
using Verifier.Checks;

namespace Verifier.Output;

/// <summary>
/// Writes verification results to stdout as the versioned snake_case JSON contract consumed by the host.
/// </summary>
public static class ResultWriter
{
    private const int SchemaVersion = 2;

    private static readonly JsonSerializerOptions JsonOptions = new()
    {
        PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
        DefaultIgnoreCondition = JsonIgnoreCondition.Never,
    };

    /// <summary>Writes an infrastructure failure (no checks could run) as a versioned result to stdout.</summary>
    public static void WriteError(string? sha256, string error)
    {
        Write(new VerificationOutput(SchemaVersion, CheckRegistry.ChecksVersion, sha256, null, [], error));
    }

    /// <summary>
    /// Writes a completed verification result, carrying the archive metadata and per-check outcomes, to stdout.
    /// </summary>
    public static void WriteResult(string? sha256, ArchiveInfo? archive, List<CheckResult> checks)
    {
        Write(new VerificationOutput(SchemaVersion, CheckRegistry.ChecksVersion, sha256, archive, checks, null));
    }

    private static void Write(VerificationOutput output)
    {
        Console.WriteLine(JsonSerializer.Serialize(output, JsonOptions));
    }
}
