namespace Verifier.Checks;

/// <summary>The extracted archive state handed to each post-extraction check.</summary>
public sealed record CheckContext(
    string ExtractDir,
    List<string> FileTree,
    long ArchiveSize);
