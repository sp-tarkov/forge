using Verifier.Output;

namespace Verifier.Checks;

/// <summary>
/// The post-extraction checks to run, in order.
///
/// To add a check: implement IVerificationCheck in this directory, register an instance in the Checks array, bump
/// ChecksVersion, and add a matching VerificationCheckType case on the host for its label and description. Emit new
/// checks with ReportOnly=true until they are trusted to enforce.
/// </summary>
public static class CheckRegistry
{
    /// <summary>
    /// The version of the check set reported to the host, bumped whenever a check is added, removed, or changed.
    /// </summary>
    public const string ChecksVersion = "2";

    private static readonly IVerificationCheck[] Checks = [new DllGuidMatchCheck(), new DllVersionMatchCheck()];

    /// <summary>Runs every registered check, recording a check that throws as a failure of that check.</summary>
    public static List<CheckResult> RunAll(CheckContext context)
    {
        List<CheckResult> results = [];

        foreach (IVerificationCheck check in Checks)
        {
            try
            {
                results.Add(check.Run(context));
            }
            catch (Exception ex)
            {
                results.Add(CheckResult.Failed(check.Name, $"Check failed to run: {ex.Message}"));
            }
        }

        return results;
    }
}
