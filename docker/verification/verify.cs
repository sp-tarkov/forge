#:package SharpCompress@0.40.0
#:property PublishAot=false

using System.IO.Compression;
using System.Security.Cryptography;
using System.Text.Json;
using SharpCompress.Archives;
using SharpCompress.Archives.SevenZip;
using SharpCompress.Common;
using SharpCompress.Readers;

const int SchemaVersion = 2;
const string ChecksVersion = "1";

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
        WriteResult(sha256, null, [FailedCheck("archive_extraction", extractionError)]);
        return;
    }

    (List<string> fileTree, int symlinksRemoved, bool fileTreeTruncated, string? postError) = PostExtractionScan(extractDir, archiveSize, maxRatio, maxExtractedSize, maxFileTreeEntries);
    if (postError is not null)
    {
        WriteResult(sha256, null, [FailedCheck("archive_extraction", postError)]);
        return;
    }

    ArchiveInfo archive = new(fileTree, fileTreeTruncated, symlinksRemoved);
    List<CheckResult> checks = [PassedCheck("archive_extraction")];
    checks.AddRange(RunPostExtractionChecks(new CheckContext(extractDir, fileTree, archiveSize)));
    WriteResult(sha256, archive, checks);
}
catch (Exception ex)
{
    WriteError(null, $"Unexpected error: {ex.Message}");
}

/// <summary>
/// The post-extraction checks to run, in order. To add a check: implement a static method taking a CheckContext and
/// returning a CheckResult, register it here, bump ChecksVersion, and add a matching VerificationCheckType case on the
/// host for its label and description. Emit new checks with ReportOnly=true until they are trusted to enforce.
/// </summary>
static (string Name, Func<CheckContext, CheckResult> Run)[] PostExtractionChecks()
{
    return [];
}

/// <summary>Runs every registered post-extraction check, recording a check that throws as a failure of that check.</summary>
static List<CheckResult> RunPostExtractionChecks(CheckContext context)
{
    List<CheckResult> results = [];

    foreach ((string name, Func<CheckContext, CheckResult> run) in PostExtractionChecks())
    {
        try
        {
            results.Add(run(context));
        }
        catch (Exception ex)
        {
            results.Add(FailedCheck(name, $"Check failed to run: {ex.Message}"));
        }
    }

    return results;
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

            // Extracts every entry in one sequential pass.
            using IReader reader = archive.ExtractAllEntries();
            while (reader.MoveToNextEntry())
            {
                IEntry entry = reader.Entry;
                if (entry.Key is null || entry.IsDirectory)
                {
                    continue;
                }

                string destPath = Path.GetFullPath(Path.Combine(extractDir, entry.Key));
                if (destPath != extractDirRoot && !destPath.StartsWith(extractDirFull, StringComparison.Ordinal))
                {
                    return $"Archive contains an entry that resolves outside the extraction directory: {entry.Key}";
                }

                Directory.CreateDirectory(Path.GetDirectoryName(destPath)!);
                using Stream source = reader.OpenEntryStream();
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
    catch (OutOfMemoryException)
    {
        return "Extracting the archive requires more memory than verification allows. This usually means it was created with an unusually large dictionary size; it should be archived again with a smaller dictionary (64 MB is plenty).";
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

/// <summary>Writes an infrastructure failure (no checks could run) as a versioned result to stdout.</summary>
void WriteError(string? sha256, string error)
{
    VerificationOutput output = new(SchemaVersion, ChecksVersion, sha256, null, [], error);

    Console.WriteLine(JsonSerializer.Serialize(output, jsonOptions));
}

/// <summary>Writes a completed verification result, carrying the archive metadata and per-check outcomes, to stdout.</summary>
void WriteResult(string? sha256, ArchiveInfo? archive, List<CheckResult> checks)
{
    VerificationOutput output = new(SchemaVersion, ChecksVersion, sha256, archive, checks, null);

    Console.WriteLine(JsonSerializer.Serialize(output, jsonOptions));
}

/// <summary>Builds a passing check.</summary>
static CheckResult PassedCheck(string name, bool reportOnly = false, Dictionary<string, object?>? data = null)
{
    return new CheckResult(name, "passed", reportOnly, null, data ?? new Dictionary<string, object?>());
}

/// <summary>Builds a failing check.</summary>
static CheckResult FailedCheck(string name, string message, bool reportOnly = false, Dictionary<string, object?>? data = null)
{
    return new CheckResult(name, "failed", reportOnly, message, data ?? new Dictionary<string, object?>());
}

/// <summary>The versioned container output contract consumed by the host.</summary>
sealed record VerificationOutput(
    int SchemaVersion,
    string ChecksVersion,
    string? Sha256,
    ArchiveInfo? Archive,
    List<CheckResult> Checks,
    string? Error);

/// <summary>Metadata about the extracted archive.</summary>
sealed record ArchiveInfo(
    List<string> FileTree,
    bool FileTreeTruncated,
    int SymlinksRemoved);

/// <summary>The extracted archive state handed to each post-extraction check.</summary>
sealed record CheckContext(
    string ExtractDir,
    List<string> FileTree,
    long ArchiveSize);

/// <summary>The outcome of a single check. A report-only check is recorded but does not fail the verification.</summary>
sealed record CheckResult(
    string Name,
    string Status,
    bool ReportOnly,
    string? Message,
    Dictionary<string, object?> Data);

/// <summary>Raised when an archive extracts past the absolute or ratio size limit.</summary>
sealed class ExtractionLimitException(string message) : Exception(message);
