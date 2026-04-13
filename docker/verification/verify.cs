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

    (List<string> fileTree, int symlinksRemoved, string? postError) = PostExtractionScan(extractDir, archiveSize, maxRatio, maxExtractedSize);
    if (postError is not null)
    {
        WriteError(sha256, postError);
        return;
    }

    WriteSuccess(sha256, fileTree, symlinksRemoved);
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

/// <summary>Validates and extracts an archive in a single pass, checking for path traversal and zip bombs.</summary>
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
        string extractDirFull = Path.GetFullPath(extractDir) + Path.DirectorySeparatorChar;

        if (extension.Equals("zip", StringComparison.OrdinalIgnoreCase))
        {
            using ZipArchive zip = ZipFile.OpenRead(archiveFile);

            long totalUncompressed = 0;
            foreach (ZipArchiveEntry entry in zip.Entries)
            {
                if (ContainsPathTraversal(entry.FullName))
                {
                    return $"Archive contains path traversal entry: {entry.FullName}";
                }

                totalUncompressed += entry.Length;
            }

            string? sizeError = CheckSizeLimits(totalUncompressed, archiveSize, maxRatio, maxExtractedSize);
            if (sizeError is not null)
            {
                return sizeError;
            }

            foreach (ZipArchiveEntry entry in zip.Entries)
            {
                string destPath = Path.GetFullPath(Path.Combine(extractDir, entry.FullName));
                if (!destPath.StartsWith(extractDirFull, StringComparison.Ordinal) &&
                    destPath != Path.GetFullPath(extractDir))
                {
                    continue;
                }

                if (string.IsNullOrEmpty(entry.Name))
                {
                    Directory.CreateDirectory(destPath);
                }
                else
                {
                    Directory.CreateDirectory(Path.GetDirectoryName(destPath)!);
                    entry.ExtractToFile(destPath, overwrite: true);
                }
            }
        }
        else if (extension.Equals("7z", StringComparison.OrdinalIgnoreCase))
        {
            using SevenZipArchive archive = SevenZipArchive.Open(archiveFile);

            long totalUncompressed = 0;
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

                totalUncompressed += entry.Size;
            }

            string? sizeError = CheckSizeLimits(totalUncompressed, archiveSize, maxRatio, maxExtractedSize);
            if (sizeError is not null)
            {
                return sizeError;
            }

            foreach (SevenZipArchiveEntry entry in archive.Entries.Where(e => !e.IsDirectory))
            {
                if (entry.Key is null)
                {
                    continue;
                }

                string destPath = Path.GetFullPath(Path.Combine(extractDir, entry.Key));
                if (!destPath.StartsWith(extractDirFull, StringComparison.Ordinal) &&
                    destPath != Path.GetFullPath(extractDir))
                {
                    continue;
                }

                Directory.CreateDirectory(Path.GetDirectoryName(destPath)!);
                entry.WriteToFile(destPath, new ExtractionOptions { ExtractFullPath = false, Overwrite = true });
            }
        }
        else
        {
            return $"Unsupported archive extension: {extension}";
        }

        return null;
    }
    catch (Exception ex)
    {
        return $"Failed to extract {extension.ToUpperInvariant()} archive: {ex.Message}";
    }
}

/// <summary>
/// Scans the extracted directory in a single pass: removes symlinks, checks size limits, and builds the file tree.
/// </summary>
static (List<string> FileTree, int SymlinksRemoved, string? Error) PostExtractionScan(
    string extractDir,
    long archiveSize,
    int maxRatio,
    long maxExtractedSize)
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
            return ([], 0, $"Extracted archive contains path traversal: {relativePath}");
        }

        totalSize += new FileInfo(fullPath).Length;
        fileTree.Add(relativePath);
    }

    if (totalSize > maxExtractedSize)
    {
        return ([], 0, $"Extracted size ({totalSize} bytes) exceeds maximum ({maxExtractedSize} bytes)");
    }

    if (archiveSize > 0)
    {
        long ratio = totalSize / archiveSize;
        if (ratio > maxRatio)
        {
            return ([], 0, $"Actual extraction ratio ({ratio}:1) exceeds maximum ({maxRatio}:1) — potential zip bomb");
        }
    }

    fileTree.Sort(StringComparer.Ordinal);

    return (fileTree, symlinksRemoved, null);
}

/// <summary>Checks whether a file path contains directory traversal sequences.</summary>
static bool ContainsPathTraversal(string path)
{
    return path.Contains("../", StringComparison.Ordinal) ||
           path.Contains("..\\", StringComparison.Ordinal) ||
           path.Contains(".." + Path.DirectorySeparatorChar, StringComparison.Ordinal) ||
           path.Contains(".." + Path.AltDirectorySeparatorChar, StringComparison.Ordinal);
}

/// <summary>Returns an error if the uncompressed size exceeds absolute or ratio limits.</summary>
static string? CheckSizeLimits(long totalUncompressed, long archiveSize, int maxRatio, long maxExtractedSize)
{
    if (totalUncompressed > maxExtractedSize)
    {
        return $"Archive uncompressed size ({totalUncompressed} bytes) exceeds maximum ({maxExtractedSize} bytes)";
    }

    if (archiveSize > 0)
    {
        long ratio = totalUncompressed / archiveSize;
        if (ratio > maxRatio)
        {
            return $"Archive compression ratio ({ratio}:1) exceeds maximum ({maxRatio}:1) — potential zip bomb";
        }
    }

    return null;
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
void WriteSuccess(string sha256, List<string> fileTree, int symlinksRemoved)
{
    var result = new
    {
        DownloadedSha256 = sha256,
        ArchiveOk = true,
        FileTree = fileTree,
        SymlinksRemoved = symlinksRemoved,
        Error = (string?)null,
    };

    Console.WriteLine(JsonSerializer.Serialize(result, jsonOptions));
}
