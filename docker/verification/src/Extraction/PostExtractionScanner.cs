namespace Verifier.Extraction;

/// <summary>
/// Scans the extracted directory, removes symlinks, checks size limits, and builds the file tree.
/// </summary>
public static class PostExtractionScanner
{
    /// <summary>
    /// Scans the extraction directory and returns the capped file tree, or an error when the archive contains no files
    /// or a limit is breached.
    /// </summary>
    public static ScanResult Scan(VerifierOptions options)
    {
        List<string> fileTree = [];
        int symlinksRemoved = 0;
        long totalSize = 0;

        foreach (string fullPath in Directory.GetFileSystemEntries(options.ExtractDir, "*", SearchOption.AllDirectories))
        {
            FileAttributes attributes = File.GetAttributes(fullPath);

            if (attributes.HasFlag(FileAttributes.ReparsePoint))
            {
                File.Delete(fullPath);
                symlinksRemoved++;
                continue;
            }

            if (attributes.HasFlag(FileAttributes.Directory))
            {
                continue;
            }

            string relativePath = Path.GetRelativePath(options.ExtractDir, fullPath);

            if (relativePath.Contains("../", StringComparison.Ordinal) || relativePath.StartsWith("..", StringComparison.Ordinal))
            {
                return new ScanResult([], 0, false, $"Extracted archive contains path traversal: {relativePath}");
            }

            totalSize += new FileInfo(fullPath).Length;
            fileTree.Add(relativePath);
        }

        if (fileTree.Count == 0)
        {
            return new ScanResult([], 0, false, "No files could be found within the archive");
        }

        if (totalSize > options.MaxExtractedSize)
        {
            return new ScanResult([], 0, false, $"Extracted size ({totalSize} bytes) exceeds maximum ({options.MaxExtractedSize} bytes)");
        }

        if (options.ArchiveSize > 0)
        {
            long ratio = totalSize / options.ArchiveSize;
            if (ratio > options.MaxRatio)
            {
                return new ScanResult([], 0, false, $"Actual extraction ratio ({ratio}:1) exceeds maximum ({options.MaxRatio}:1): potential zip bomb");
            }
        }

        fileTree.Sort(StringComparer.Ordinal);

        bool truncated = fileTree.Count > options.MaxFileTreeEntries;
        if (truncated)
        {
            fileTree = fileTree.GetRange(0, options.MaxFileTreeEntries);
        }

        return new ScanResult(fileTree, symlinksRemoved, truncated, null);
    }
}
