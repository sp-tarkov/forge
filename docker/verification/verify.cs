#:package SharpCompress@0.40.0
#:property PublishAot=false

using System.IO.Compression;
using System.Security.Cryptography;
using System.Text.Json;
using SharpCompress.Archives;
using SharpCompress.Archives.SevenZip;
using SharpCompress.Common;

string inputFile = "/input/archive";
string extension = Environment.GetEnvironmentVariable("ARCHIVE_EXTENSION") ?? "zip";
long archiveSize = long.TryParse(Environment.GetEnvironmentVariable("ARCHIVE_SIZE"), out long s) ? s : 0L;
int maxRatio = int.TryParse(Environment.GetEnvironmentVariable("MAX_EXTRACTION_RATIO"), out int r) ? r : 100;
long maxExtractedSize = long.TryParse(Environment.GetEnvironmentVariable("MAX_EXTRACTED_SIZE"), out long m) ? m : 2L * 1024 * 1024 * 1024;
int maxFileTreeEntries = int.TryParse(Environment.GetEnvironmentVariable("MAX_FILE_TREE_ENTRIES"), out int f) ? f : 10000;

string workDir = "/tmp/work";
string extractDir = Path.Combine(workDir, "extracted");

var jsonOptions = new JsonSerializerOptions
{
    PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
    DefaultIgnoreCondition = System.Text.Json.Serialization.JsonIgnoreCondition.Never,
};

try
{
    if (!File.Exists(inputFile))
    {
        WriteError(null, $"Input file not found at {inputFile}");
        return;
    }

    string sha256 = ComputeSha256(inputFile);

    Directory.CreateDirectory(extractDir);

    string? extractionError = ValidateAndExtract(inputFile, extension, extractDir, archiveSize, maxRatio, maxExtractedSize);
    if (extractionError is not null)
    {
        WriteError(sha256, extractionError);
        return;
    }

    (List<string> fileTree, int symlinksRemoved, bool fileTreeTruncated, string? postError) = PostExtractionScan(extractDir, archiveSize, maxRatio, maxExtractedSize, maxFileTreeEntries);
    if (postError is not null)
    {
        WriteError(sha256, postError);
        return;
    }

    WriteSuccess(sha256, fileTree, symlinksRemoved, fileTreeTruncated);
}
catch (Exception ex)
{
    WriteError(null, $"Unexpected error: {ex.Message}");
}

/// <summary>Computes the SHA-256 hash of a file.</summary>
static string ComputeSha256(string filePath)
{
    using FileStream stream = File.OpenRead(filePath);
    byte[] hash = SHA256.HashData(stream);

    return Convert.ToHexStringLower(hash);
}

/// <summary>
/// Validates and extracts an archive, enforcing the size and ratio limits against the bytes actually written rather
/// than the sizes the archive declares. The declared total is only used for a cheap fast-fail on honestly oversized
/// archives; a declared size cannot be trusted, so the per-entry streaming copy is the real limit.
/// </summary>
static string? ValidateAndExtract(
    string archiveFile,
    string extension,
    string extractDir,
    long archiveSize,
    int maxRatio,
    long maxExtractedSize)
{
    try
    {
        string extractDirRoot = Path.GetFullPath(extractDir);
        string extractDirFull = extractDirRoot + Path.DirectorySeparatorChar;
        long maxByRatio = ComputeRatioLimit(archiveSize, maxRatio);
        long totalWritten = 0;

        if (extension.Equals("zip", StringComparison.OrdinalIgnoreCase))
        {
            using ZipArchive zip = ZipFile.OpenRead(archiveFile);

            long declaredTotal = 0;
            foreach (ZipArchiveEntry entry in zip.Entries)
            {
                if (ContainsPathTraversal(entry.FullName))
                {
                    return $"Archive contains path traversal entry: {entry.FullName}";
                }

                declaredTotal += entry.Length;
            }

            if (declaredTotal > maxExtractedSize)
            {
                return $"Archive declares an uncompressed size ({declaredTotal} bytes) exceeding maximum ({maxExtractedSize} bytes)";
            }

            foreach (ZipArchiveEntry entry in zip.Entries)
            {
                string destPath = Path.GetFullPath(Path.Combine(extractDir, entry.FullName));
                if (destPath != extractDirRoot && !destPath.StartsWith(extractDirFull, StringComparison.Ordinal))
                {
                    return $"Archive contains an entry that resolves outside the extraction directory: {entry.FullName}";
                }

                if (string.IsNullOrEmpty(entry.Name))
                {
                    Directory.CreateDirectory(destPath);
                }
                else
                {
                    Directory.CreateDirectory(Path.GetDirectoryName(destPath)!);
                    using Stream source = entry.Open();
                    totalWritten = ExtractEntry(source, destPath, totalWritten, maxExtractedSize, maxByRatio, maxRatio);
                }
            }
        }
        else if (extension.Equals("7z", StringComparison.OrdinalIgnoreCase))
        {
            using SevenZipArchive archive = SevenZipArchive.Open(archiveFile);

            long declaredTotal = 0;
            foreach (SevenZipArchiveEntry entry in archive.Entries)
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

            foreach (SevenZipArchiveEntry entry in archive.Entries.Where(e => !e.IsDirectory))
            {
                if (entry.Key is null)
                {
                    continue;
                }

                string destPath = Path.GetFullPath(Path.Combine(extractDir, entry.Key));
                if (destPath != extractDirRoot && !destPath.StartsWith(extractDirFull, StringComparison.Ordinal))
                {
                    return $"Archive contains an entry that resolves outside the extraction directory: {entry.Key}";
                }

                Directory.CreateDirectory(Path.GetDirectoryName(destPath)!);
                using Stream source = entry.OpenEntryStream();
                totalWritten = ExtractEntry(source, destPath, totalWritten, maxExtractedSize, maxByRatio, maxRatio);
            }
        }
        else
        {
            return $"Unsupported archive extension: {extension}";
        }

        return null;
    }
    catch (ExtractionLimitException ex)
    {
        return ex.Message;
    }
    catch (Exception ex)
    {
        return $"Failed to extract {extension.ToUpperInvariant()} archive: {ex.Message}";
    }
}

/// <summary>
/// Scans the extracted directory in a single pass: removes symlinks, checks size limits, and builds the file tree.
/// The tree is capped at maxFileTreeEntries so the reported list, and the JSON written to stdout, cannot grow without
/// bound; the flag records whether entries were dropped.
/// </summary>
static (List<string> FileTree, int SymlinksRemoved, bool Truncated, string? Error) PostExtractionScan(
    string extractDir,
    long archiveSize,
    int maxRatio,
    long maxExtractedSize,
    int maxFileTreeEntries)
{
    List<string> fileTree = [];
    int symlinksRemoved = 0;
    long totalSize = 0;

    foreach (string fullPath in Directory.GetFileSystemEntries(extractDir, "*", SearchOption.AllDirectories))
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

        string relativePath = Path.GetRelativePath(extractDir, fullPath);

        if (relativePath.Contains("../", StringComparison.Ordinal) ||
            relativePath.StartsWith("..", StringComparison.Ordinal))
        {
            return ([], 0, false, $"Extracted archive contains path traversal: {relativePath}");
        }

        totalSize += new FileInfo(fullPath).Length;
        fileTree.Add(relativePath);
    }

    if (totalSize > maxExtractedSize)
    {
        return ([], 0, false, $"Extracted size ({totalSize} bytes) exceeds maximum ({maxExtractedSize} bytes)");
    }

    if (archiveSize > 0)
    {
        long ratio = totalSize / archiveSize;
        if (ratio > maxRatio)
        {
            return ([], 0, false, $"Actual extraction ratio ({ratio}:1) exceeds maximum ({maxRatio}:1): potential zip bomb");
        }
    }

    fileTree.Sort(StringComparer.Ordinal);

    bool truncated = fileTree.Count > maxFileTreeEntries;
    if (truncated)
    {
        fileTree = fileTree.GetRange(0, maxFileTreeEntries);
    }

    return (fileTree, symlinksRemoved, truncated, null);
}

/// <summary>Checks whether a file path contains directory traversal sequences.</summary>
static bool ContainsPathTraversal(string path)
{
    return path.Contains("../", StringComparison.Ordinal) ||
           path.Contains("..\\", StringComparison.Ordinal) ||
           path.Contains(".." + Path.DirectorySeparatorChar, StringComparison.Ordinal) ||
           path.Contains(".." + Path.AltDirectorySeparatorChar, StringComparison.Ordinal);
}

/// <summary>
/// Streams a single entry to disk, aborting the moment the running total of bytes written crosses the absolute or ratio
/// limit. The breaching buffer is never written, so total bytes on disk stay within one buffer of the limit regardless
/// of what the archive declared. Returns the new running total.
/// </summary>
static long ExtractEntry(
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
/// Returns the maximum number of extracted bytes permitted by the ratio limit, or long.MaxValue when the archive size
/// is unknown or would overflow.
/// </summary>
static long ComputeRatioLimit(long archiveSize, int maxRatio)
{
    if (archiveSize <= 0 || maxRatio <= 0)
    {
        return long.MaxValue;
    }

    return maxRatio <= long.MaxValue / archiveSize ? (long)maxRatio * archiveSize : long.MaxValue;
}

/// <summary>Writes a failed verification result as JSON to stdout.</summary>
void WriteError(string? sha256, string error)
{
    var result = new
    {
        DownloadedSha256 = sha256,
        ArchiveOk = false,
        FileTree = Array.Empty<string>(),
        SymlinksRemoved = 0,
        Error = error,
    };

    Console.WriteLine(JsonSerializer.Serialize(result, jsonOptions));
}

/// <summary>Writes a successful verification result as JSON to stdout.</summary>
void WriteSuccess(string sha256, List<string> fileTree, int symlinksRemoved, bool fileTreeTruncated)
{
    var result = new
    {
        DownloadedSha256 = sha256,
        ArchiveOk = true,
        FileTree = fileTree,
        FileTreeTruncated = fileTreeTruncated,
        SymlinksRemoved = symlinksRemoved,
        Error = (string?)null,
    };

    Console.WriteLine(JsonSerializer.Serialize(result, jsonOptions));
}

/// <summary>Raised when an archive extracts past the absolute or ratio size limit.</summary>
sealed class ExtractionLimitException(string message) : Exception(message);
