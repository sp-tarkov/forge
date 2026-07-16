namespace Verifier.Extraction;

/// <summary>
/// The outcome of the post-extraction scan: the capped file tree, or the limit violation that was found.
/// </summary>
public sealed record ScanResult(
    List<string> FileTree,
    int SymlinksRemoved,
    bool Truncated,
    string? Error);
