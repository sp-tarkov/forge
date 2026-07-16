using Verifier.Inspection;

namespace Verifier.Checks;

/// <summary>The extracted archive state handed to each post-extraction check.</summary>
public sealed record CheckContext(
    string ExtractDir,
    List<string> FileTree,
    long ArchiveSize,
    string ModVersion,
    string ModGuid)
{
    private DllScanSummary? _dllScan;

    /// <summary>
    /// The mod component metadata found in the archive's DLLs, scanned once on first access and shared by every check.
    /// </summary>
    public DllScanSummary DllScan => _dllScan ??= ArchiveDllScanner.Scan(ExtractDir, FileTree);
}
