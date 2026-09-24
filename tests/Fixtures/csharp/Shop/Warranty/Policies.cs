namespace Shop.Warranty;

public sealed record WarrantyPolicy(int Months, IReadOnlySet<string> CoveredFaults)
{
    public bool Covers(string fault) => CoveredFaults.Contains(fault);

    // @sin NamespaceCycle
    public int OpenClaims(IEnumerable<Shop.Returns.Claim> claims) => claims.Count(claim => Covers(claim.Fault));
}
