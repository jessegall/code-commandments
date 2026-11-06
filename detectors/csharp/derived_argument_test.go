package csharp

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine/csharp/csharptest"
)

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
