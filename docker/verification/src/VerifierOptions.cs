namespace Verifier;

/// <summary>
/// The container inputs: the fixed archive path, work directories, and the limits passed in as environment variables
/// by the host.
/// </summary>
public sealed record VerifierOptions(
    string InputFile,
    string Extension,
    long ArchiveSize,
    int MaxRatio,
    long MaxExtractedSize,
    int MaxFileTreeEntries,
    string ExtractDir)
{
    /// <summary>Builds the options from the container environment, falling back to safe defaults.</summary>
    public static VerifierOptions FromEnvironment()
    {
        return new VerifierOptions(
            InputFile: "/input/archive",
            Extension: Environment.GetEnvironmentVariable("ARCHIVE_EXTENSION") ?? "zip",
            ArchiveSize: long.TryParse(Environment.GetEnvironmentVariable("ARCHIVE_SIZE"), out long size) ? size : 0L,
            MaxRatio: int.TryParse(Environment.GetEnvironmentVariable("MAX_EXTRACTION_RATIO"), out int ratio) ? ratio : 100,
            MaxExtractedSize: long.TryParse(Environment.GetEnvironmentVariable("MAX_EXTRACTED_SIZE"), out long extractedSize) ? extractedSize : 2L * 1024 * 1024 * 1024,
            MaxFileTreeEntries: int.TryParse(Environment.GetEnvironmentVariable("MAX_FILE_TREE_ENTRIES"), out int entries) ? entries : 10000,
            ExtractDir: "/tmp/work/extracted");
    }
}
