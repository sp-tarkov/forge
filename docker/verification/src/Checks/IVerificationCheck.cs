using Verifier.Output;

namespace Verifier.Checks;

/// <summary>A single post-extraction check run against the extracted archive.</summary>
public interface IVerificationCheck
{
    /// <summary>
    /// The stable check name reported to the host. It must have a matching VerificationCheckType case on the host so
    /// the admin and public check lists can present its label and description.
    /// </summary>
    string Name { get; }

    /// <summary>Runs the check against the extracted archive and reports its outcome.</summary>
    CheckResult Run(CheckContext context);
}
