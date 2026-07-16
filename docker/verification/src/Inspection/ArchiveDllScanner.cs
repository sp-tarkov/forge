namespace Verifier.Inspection;

/// <summary>
/// Discovers the DLLs in the extracted archive and reads their mod component metadata. Scanning is capped by per-file
/// size and total DLL count.
/// </summary>
public static class ArchiveDllScanner
{
    public const long MaxDllSizeBytes = 100L * 1024 * 1024;

    public const int MaxDllCount = 200;

    /// <summary>Reads the mod component metadata of every DLL in the file tree, within the scanning caps.</summary>
    public static DllScanSummary Scan(string extractDir, List<string> fileTree)
    {
        List<DllFinding> findings = [];
        int scanned = 0;
        int skippedBySize = 0;
        bool truncated = false;

        foreach (string relativePath in fileTree)
        {
            if (!relativePath.EndsWith(".dll", StringComparison.OrdinalIgnoreCase))
            {
                continue;
            }

            if (scanned >= MaxDllCount)
            {
                truncated = true;
                break;
            }

            FileInfo file = new(Path.Combine(extractDir, relativePath));

            if (!file.Exists)
            {
                continue;
            }

            if (file.Length > MaxDllSizeBytes)
            {
                skippedBySize++;
                continue;
            }

            scanned++;
            findings.AddRange(DllMetadataReader.Read(file.FullName, relativePath));
        }

        return new DllScanSummary(findings, scanned, skippedBySize, truncated);
    }
}
