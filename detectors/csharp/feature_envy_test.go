package csharp

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

// TestTheThreeReportedEnvySites holds the rule to the three methods reported against it in #814, #812 and #808,
// each as it is really written rather than as its report described it.
//
// Two of them are the sin: the resolver filters a collection the catalog hands out, and the filter builder
// projects the criteria's own rids outside the criteria. The third writes each of a request's questions into a
// StringBuilder as text, which is formatting — the knowledge in it is the prompt's wording, which cannot move
// onto a value object — and the rule has no business there.
func TestTheThreeReportedEnvySites(t *testing.T) {
	const criteria = `using System.Collections.Generic;

namespace Dealers.Search.Contracts;

public sealed record Rid(string Value)
{
    public string ToPublicId() => this.Value;
}

public sealed record DealerSearchCriteria
{
    public IReadOnlyList<Rid> BrandRids { get; init; } = [];

    public bool BrandsInStockOnly { get; init; }
}
`
	const builder = `using System.Collections.Generic;
using System.Linq;
using Dealers.Search.Contracts;

namespace Dealers.Discovery;

public sealed class Query
{
    public string Field { get; set; } = "";
}

public static class DealerFilterBuilder
{
    private static void AddTerms(List<Query> filters, string field, string[] values)
    {
        filters.Add(new Query { Field = field });
    }

    private static void AddBrandRidsFilter(List<Query> filters, DealerSearchCriteria criteria)
    {
        if (criteria.BrandRids.Count == 0)
        {
            return;
        }

        AddTerms(
            filters,
            criteria.BrandsInStockOnly ? "brandRidsInStock" : "brandRids",
            [.. criteria.BrandRids.Select(rid => rid.ToPublicId())]);
    }
}
`
	const request = `using System.Collections.Generic;

namespace Copywriting.Values;

public sealed record ChannelProfile
{
    public string ChannelType { get; init; } = "";

    public string ContentType { get; init; } = "";

    public int? MaxTitleLength { get; init; }

    public int? MaxDescriptionLength { get; init; }

    public string? StyleGuidance { get; init; }

    public IReadOnlyList<string> Questions { get; init; } = [];
}

public sealed record ContentRequest
{
    public ChannelProfile Channel { get; init; } = new ChannelProfile();
}
`
	const prompts = `using System.Text;
using Copywriting.Values;

namespace Copywriting.Infrastructure.Gemini;

public static class CopywritingPrompts
{
    private static string GetChannelStyleProfile(string channel) => channel;

    private static void AppendChannelRequirements(StringBuilder sb, ContentRequest request)
    {
        sb.AppendLine($"""

            ## Channel Requirements
            - Channel: {request.Channel.ChannelType}
            - Content Format: {request.Channel.ContentType}
            """);

        if (request.Channel.MaxTitleLength is not null)
            sb.AppendLine($"- Max Title Length: {request.Channel.MaxTitleLength} characters");
        if (request.Channel.MaxDescriptionLength is not null)
            sb.AppendLine($"- Max Description Length: {request.Channel.MaxDescriptionLength} characters (STRICT)");
        if (request.Channel.StyleGuidance is not null)
            sb.AppendLine($"- Style Guidance: {request.Channel.StyleGuidance}");
        if (request.Channel.Questions.Count > 0)
        {
            sb.AppendLine("- Description Structure: answer each question in turn.");
            foreach (var question in request.Channel.Questions)
                sb.AppendLine($"  - {question}");
        }

        sb.AppendLine();
        sb.AppendLine(GetChannelStyleProfile(request.Channel.ChannelType));
    }
}
`
	const catalog = `using System.Collections.Generic;

namespace Core.Operations;

public sealed record OperationDeclaration(string Code);

public interface IOperationCatalog
{
    IReadOnlyList<OperationDeclaration> All { get; }
}
`
	const resolver = `using System.Collections.Generic;
using System.Linq;
using Core.Operations;

namespace Api.Operations;

public sealed record OperationSummary(string Code);

public static class OperationSummaryProjection
{
    public static OperationSummary Project(OperationDeclaration declaration, IOperationCatalog catalog) => new OperationSummary(declaration.Code);
}

public static class SchemaOperations
{
    public static IReadOnlySet<string> ExposedOn(string schema) => new HashSet<string>();
}

public static class OperationCatalogQueries
{
    public static IReadOnlyList<OperationSummary> OperationCatalog(IOperationCatalog catalog, string schema)
    {
        var exposed = SchemaOperations.ExposedOn(schema);

        return catalog.All
            .Where(d => exposed.Contains(d.Code))
            .Select(d => OperationSummaryProjection.Project(d, catalog))
            .ToList();
    }
}
`
	codebase := csharptest.FromSource(t, map[string]string{
		"Dealers.Search.Contracts/DealerSearchCriteria.cs":    criteria,
		"Dealers.Discovery/DealerFilterBuilder.cs":            builder,
		"Copywriting/Values/ContentRequest.cs":                request,
		"Copywriting.Infrastructure/Gemini/CopywritingPrompts.cs": prompts,
		"Core/Operations/IOperationCatalog.cs":                 catalog,
		"Api/Operations/OperationCatalogQueries.cs":            resolver,
	})

	fired := map[string]bool{}
	for _, found := range (FeatureEnvyDetector{}).Find(codebase) {
		fired[found.Scope()] = true
	}

	for scope, want := range map[string]bool{
		"MethodDeclaration OperationCatalog":          true,
		"MethodDeclaration AddBrandRidsFilter":        true,
		"MethodDeclaration AppendChannelRequirements": false,
	} {
		if fired[scope] != want {
			t.Errorf("%s: fired %v, want %v", scope, fired[scope], want)
		}
	}
}

