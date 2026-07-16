namespace Verifier.Output;

/// <summary>Metadata about the extracted archive.</summary>
public sealed record ArchiveInfo(
    List<string> FileTree,
    bool FileTreeTruncated,
    int SymlinksRemoved);
