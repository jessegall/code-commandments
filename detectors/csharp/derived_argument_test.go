package csharp

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

// costLines is issue 706's shape: two validators each hand one store method three ids read off the command they
// check. The second validator's command is TYPE.
const costLines = `using System;
using System.Threading;
using System.Threading.Tasks;

namespace Inventory;

public sealed record RemoveCostLineCommand(Guid DealerId, Guid ItemId, Guid CostLineId);

public sealed record ReviseCostLineCommand(Guid DealerId, Guid ItemId, Guid CostLineId, decimal Amount);

public sealed class DocumentStore
{
    public Task<bool> HasCostLineAsync(Guid dealerId, Guid itemId, Guid costLineId, CancellationToken ct) =>
        Task.FromResult(dealerId != itemId && itemId != costLineId);
}

public sealed class RemoveCostLineValidator(DocumentStore store)
{
    public Task<bool> Exists(RemoveCostLineCommand command, CancellationToken ct) =>
        store.HasCostLineAsync(command.DealerId, command.ItemId, command.CostLineId, ct);
}

public sealed class ReviseCostLineValidator(DocumentStore store)
{
    public Task<bool> Exists(TYPE command, CancellationToken ct) =>
        store.HasCostLineAsync(command.DealerId, command.ItemId, command.CostLineId, ct);
}
`

// TestPiecesOfObjectsOfDifferentTypesAreNoDerivedArgument holds the rule to an object the method could take: the
// pieces every call hands must be read off objects of one type, since a method takes one parameter type. Two
// command types sharing no type of their own leave nothing to take whole; one command type still does.
func TestPiecesOfObjectsOfDifferentTypesAreNoDerivedArgument(t *testing.T) {
	for name, test := range map[string]struct {
		command string
		want    int
	}{
		"two unrelated command types": {"ReviseCostLineCommand", 0},
		"one command type":            {"RemoveCostLineCommand", 2},
	} {
		t.Run(name, func(t *testing.T) {
			source := strings.ReplaceAll(costLines, "TYPE", test.command)
			found := DerivedArgumentDetector{}.Find(csharptest.FromSource(t, map[string]string{"Inventory/CostLines.cs": source}))

			if len(found) != test.want {
				t.Fatalf("found %d derived arguments, want %d: %v", len(found), test.want, locations(found))
			}
		})
	}
}
