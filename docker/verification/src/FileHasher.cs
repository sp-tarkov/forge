using System.Security.Cryptography;

namespace Verifier;

/// <summary>Hashes the archive file for the verification result.</summary>
public static class FileHasher
{
    /// <summary>Computes the SHA-256 hash of a file as a lowercase hex string.</summary>
    public static string Sha256(string filePath)
    {
        using FileStream stream = File.OpenRead(filePath);
        byte[] hash = SHA256.HashData(stream);

        return Convert.ToHexStringLower(hash);
    }
}
