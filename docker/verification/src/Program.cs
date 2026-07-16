using Verifier;
using Verifier.Checks;
using Verifier.Extraction;
using Verifier.Output;

const string archiveExtractionCheckName = "archive_extraction";

VerifierOptions options = VerifierOptions.FromEnvironment();

try
{
    if (!File.Exists(options.InputFile))
    {
        ResultWriter.WriteError(null, $"Input file not found at {options.InputFile}");
        return;
    }

    string sha256 = FileHasher.Sha256(options.InputFile);

    Directory.CreateDirectory(options.ExtractDir);

    string? extractionError = ArchiveExtractor.ValidateAndExtract(options);
    if (extractionError is not null)
    {
        ResultWriter.WriteResult(sha256, null, [CheckResult.Failed(archiveExtractionCheckName, extractionError)]);
        return;
    }

    ScanResult scan = PostExtractionScanner.Scan(options);
    if (scan.Error is not null)
    {
        ResultWriter.WriteResult(sha256, null, [CheckResult.Failed(archiveExtractionCheckName, scan.Error)]);
        return;
    }

    ArchiveInfo archive = new(scan.FileTree, scan.Truncated, scan.SymlinksRemoved);
    List<CheckResult> checks = [CheckResult.Passed(archiveExtractionCheckName)];
    checks.AddRange(CheckRegistry.RunAll(new CheckContext(options.ExtractDir, scan.FileTree, options.ArchiveSize, options.ModVersion, options.ModGuid)));
    ResultWriter.WriteResult(sha256, archive, checks);
}
catch (Exception ex)
{
    ResultWriter.WriteError(null, $"Unexpected error: {ex.Message}");
}
