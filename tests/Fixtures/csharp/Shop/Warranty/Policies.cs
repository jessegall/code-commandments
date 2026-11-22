using Shop.Returns;

namespace Shop.Warranty;

public sealed record WarrantyPolicy(int Months, IReadOnlySet<string> CoveredFaults)
{
    public bool Covers(string fault) => CoveredFaults.Contains(fault);

    public int OpenClaims(IEnumerable<Claim> claims) => claims.Count(claim => Covers(claim.Fault));
}
