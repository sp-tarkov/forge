using SharpCompress.Archives;
using SharpCompress.Archives.SevenZip;
using SharpCompress.Archives.Zip;
using SharpCompress.Common;
using SharpCompress.Readers;

namespace Verifier.Extraction;

/// <summary>
/// Validates and extracts an archive, enforcing the size and ratio limits against the bytes actually written rather
/// than the sizes the archive declares.
/// </summary>
public static class ArchiveExtractor
{
    /// <summary>Extracts the archive into the extraction directory.</summary>
    public static string? ValidateAndExtract(VerifierOptions options)
    {
        try
        {
            string extractDirRoot = Path.GetFullPath(options.ExtractDir);
            string extractDirFull = extractDirRoot + Path.DirectorySeparatorChar;
            long maxByRatio = ComputeRatioLimit(options.ArchiveSize, options.MaxRatio);

            if (options.Extension.Equals("zip", StringComparison.OrdinalIgnoreCase))
            {
                return ExtractZip(options, extractDirRoot, extractDirFull, maxByRatio);
            }

            if (options.Extension.Equals("7z", StringComparison.OrdinalIgnoreCase))
            {
                return ExtractSevenZip(options, extractDirRoot, extractDirFull, maxByRatio);
            }

            return $"Unsupported archive extension: {options.Extension}";
        }
        catch (ExtractionLimitException ex)
        {
            return ex.Message;
        }
        catch (OutOfMemoryException)
        {
            return "Extracting the archive requires more memory than verification allows. This usually means it was created with an unusually large dictionary size; it should be archived again with a smaller dictionary (64 MB is plenty).";
        }
        catch (Exception ex)
        {
            return $"Failed to extract {options.Extension.ToUpperInvariant()} archive: {ex.Message}";
        }
    }

    /// <summary>
    /// Extracts a zip archive entry by entry. Reads the zip using SharpCompress, which handles LZMA, PPMd, and BZip2.
    /// </summary>
    private static string? ExtractZip(VerifierOptions options, string extractDirRoot, string extractDirFull, long maxByRatio)
    {
        using IArchive zip = ZipArchive.OpenArchive(new BufferedArchiveStream(options.InputFile));

        string? declaredError = PrecheckDeclaredEntries(zip, options.MaxExtractedSize);
        if (declaredError is not null)
        {
            return declaredError;
        }

        long totalWritten = 0;
        foreach (IArchiveEntry entry in zip.Entries)
        {
            if (entry.Key is null)
            {
                continue;
            }

            string destPath = Path.GetFullPath(Path.Combine(options.ExtractDir, entry.Key));
            if (destPath != extractDirRoot && !destPath.StartsWith(extractDirFull, StringComparison.Ordinal))
            {
                return $"Archive contains an entry that resolves outside the extraction directory: {entry.Key}";
            }

            if (entry.IsDirectory)
            {
                Directory.CreateDirectory(destPath);
            }
            else
            {
                Directory.CreateDirectory(Path.GetDirectoryName(destPath)!);
                using Stream source = entry.OpenEntryStream();
                totalWritten = ExtractEntry(source, destPath, totalWritten, options.MaxExtractedSize, maxByRatio, options.MaxRatio);
            }
        }

        return null;
    }

    /// <summary>Extracts a 7z archive by streaming every entry in one sequential pass.</summary>
    private static string? ExtractSevenZip(VerifierOptions options, string extractDirRoot, string extractDirFull, long maxByRatio)
    {
        using IArchive archive = SevenZipArchive.OpenArchive(new BufferedArchiveStream(options.InputFile));

        string? declaredError = PrecheckDeclaredEntries(archive, options.MaxExtractedSize);
        if (declaredError is not null)
        {
            return declaredError;
        }

        long totalWritten = 0;
        using IReader reader = archive.ExtractAllEntries();
        while (reader.MoveToNextEntry())
        {
            IEntry entry = reader.Entry;
            if (entry.Key is null || entry.IsDirectory)
            {
                continue;
            }

            string destPath = Path.GetFullPath(Path.Combine(options.ExtractDir, entry.Key));
            if (destPath != extractDirRoot && !destPath.StartsWith(extractDirFull, StringComparison.Ordinal))
            {
                return $"Archive contains an entry that resolves outside the extraction directory: {entry.Key}";
            }

            Directory.CreateDirectory(Path.GetDirectoryName(destPath)!);
            using Stream source = reader.OpenEntryStream();
            totalWritten = ExtractEntry(source, destPath, totalWritten, options.MaxExtractedSize, maxByRatio, options.MaxRatio);
        }

        return null;
    }

    /// <summary>
    /// Fails fast when an entry declares a path traversal or the declared uncompressed total exceeds the extraction
    /// cap. Returns an error message or null when the declared entries are acceptable.
    /// </summary>
    private static string? PrecheckDeclaredEntries(IArchive archive, long maxExtractedSize)
    {
        long declaredTotal = 0;

        foreach (IArchiveEntry entry in archive.Entries)
        {
            if (entry.Key is null)
            {
                continue;
            }

            if (ContainsPathTraversal(entry.Key))
            {
                return $"Archive contains path traversal entry: {entry.Key}";
            }

            declaredTotal += entry.Size;
        }

        if (declaredTotal > maxExtractedSize)
        {
            return $"Archive declares an uncompressed size ({declaredTotal} bytes) exceeding maximum ({maxExtractedSize} bytes)";
        }

        return null;
    }

    /// <summary>Checks whether a file path contains directory traversal sequences.</summary>
    private static bool ContainsPathTraversal(string path)
    {
        return path.Contains("../", StringComparison.Ordinal) ||
               path.Contains("..\\", StringComparison.Ordinal) ||
               path.Contains(".." + Path.DirectorySeparatorChar, StringComparison.Ordinal) ||
               path.Contains(".." + Path.AltDirectorySeparatorChar, StringComparison.Ordinal);
    }

    /// <summary>
    /// Streams a single entry to disk, aborting the moment the running total of bytes written cross the absolute or
    /// ratio limit. The breaching buffer is never written, so total bytes on the disk stay within one buffer of the
    /// limit regardless of what the archive declared.
    /// </summary>
    private static long ExtractEntry(
        Stream source,
        string destPath,
        long totalWritten,
        long maxExtractedSize,
        long maxByRatio,
        int maxRatio)
    {
        byte[] buffer = new byte[81920];
        using FileStream destination = File.Create(destPath);

        int read;
        while ((read = source.Read(buffer, 0, buffer.Length)) > 0)
        {
            totalWritten += read;

            if (totalWritten > maxExtractedSize)
            {
                throw new ExtractionLimitException($"Extracted size exceeds maximum ({maxExtractedSize} bytes)");
            }

            if (totalWritten > maxByRatio)
            {
                throw new ExtractionLimitException($"Extraction ratio exceeds maximum ({maxRatio}:1): potential zip bomb");
            }

            destination.Write(buffer, 0, read);
        }

        return totalWritten;
    }

    /// <summary>
    /// Returns the maximum number of extracted bytes permitted by the ratio limit, or long.MaxValue when the archive
    /// size is unknown or would overflow.
    /// </summary>
    private static long ComputeRatioLimit(long archiveSize, int maxRatio)
    {
        if (archiveSize <= 0 || maxRatio <= 0)
        {
            return long.MaxValue;
        }

        return maxRatio <= long.MaxValue / archiveSize ? maxRatio * archiveSize : long.MaxValue;
    }
}
