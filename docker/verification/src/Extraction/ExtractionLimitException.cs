namespace Verifier.Extraction;

/// <summary>Raised when an archive extracts past the absolute or ratio size limit.</summary>
public sealed class ExtractionLimitException(string message) : Exception(message);
