package php_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func resolvedAt(t *testing.T, codebase *engine.Codebase, written string) engine.Match {
	t.Helper()
	for _, node := range codebase.Where(func(node engine.Match) bool { return node.Written() == written }).Get() {
		if node.Node().Role == "expression" {
			return node
		}
	}

	t.Fatalf("no expression written %q in the source", written)

	return engine.Match{}
}

func TestMemberUsesTheTypeAtTheReadAndFindsNestedTraitProperties(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace Shop;

trait HasSlot { public string $slot; }
trait HasLabel { use HasSlot; }
final class RegisteredPage { use HasLabel; }
final class RegisteredSection {}

function addRoutes(): void {
    $registered = new RegisteredPage;
    echo $registered->slot;
    $registered = new RegisteredSection;
    echo $registered->slot;
}

function skipSection(bool $section): void {
    $registered = new RegisteredPage;
    if ($section) {
        $registered = new RegisteredSection;
        return;
    }
    echo $registered->slot;
}
`)
	var lines []int
	for _, read := range codebase.Where(engine.Match.ReadsUndeclaredMember).Get() {
		lines = append(lines, read.Line())
	}
	if len(lines) != 1 || lines[0] != 13 {
		t.Fatalf("got findings on %v, want only the section's missing slot on line 13", lines)
	}
}

func TestMemberReadsUseSpecializedPropertiesAndClassStringReturns(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace Shop;

interface Client {}
final class DispatchClient implements Client { public string $manifest; }
class Scope { public Client $client; }

/** @property DispatchClient $client */
final class DispatchScope extends Scope {}

final class Registry {
    /**
     * @template T of Client
     * @param class-string<T> $type
     * @return T
     */
    public function find(string $type): Client { return new $type; }
}

function manifest(DispatchScope $scope, Registry $registry, Client $client): void {
    echo $scope->client->manifest;
    echo $registry->find(type: DispatchClient::class)->manifest;
    echo $client->manifest;
}
`)
	reads := codebase.Where(engine.Match.ReadsUndeclaredMember).Get()
	if len(reads) != 1 || reads[0].Written() != "$client->manifest" {
		t.Fatalf("got %v, want only the missing property on the unqualified Client", reads)
	}
}

func TestMemberReadsLeaveWritesAndOutsideTraitsAlone(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace Shop;

trait Dispatch { use \Outside\Delivery; }
final class DeliveryDesk { use Dispatch; }
final class CustomerRecord { public function beforeDue(): void {} }

function deliver(DeliveryDesk $desk, CustomerRecord $record): void {
    $desk->arrive();
    $record->BeforeDue();
    $record->delivered = true;
    $record->attempts += 1;
}
`)
	reads := codebase.Where(engine.Match.ReadsUndeclaredMember).Get()
	if len(reads) != 1 || reads[0].Written() != "$record->attempts" {
		t.Fatalf("got %v, want only the missing property the update reads", reads)
	}
}

func TestAResolvedTypeKeepsTheTypeItWasReadOff(t *testing.T) {
	codebase := phpSource(t, `<?php

namespace Shop;

final class Money
{
    public function plus(Money $other): Money
    {
        return $other;
    }
}

final class Till
{
    private ?Money $spare = null;

    public function __construct(private Money $held) {}

    public function take(Money $paid): Money
    {
        $sum = $paid->plus($this->held);
        $maybe = $this->spare;
        $anything = $this->whatever();

        return $sum;
    }
}
`)

	for written, want := range map[string]struct {
		name     string
		nullable bool
		origin   string
	}{
		"$paid":        {"Shop\\Money", false, "written"},
		"$sum":         {"Shop\\Money", false, "written"},
		"$this->held":  {"Shop\\Money", false, "written"},
		"$this->spare": {"Shop\\Money", true, "written"},
		"$maybe":       {"Shop\\Money", true, "written"},
	} {
		t.Run(written, func(t *testing.T) {
			resolved := resolvedAt(t, codebase, written).Node().Resolved
			if resolved == nil {
				t.Fatalf("%s carries no resolved type", written)
			}

			if resolved.Name != want.name || resolved.Nullable != want.nullable || resolved.Origin != want.origin {
				t.Errorf("%s resolves to %q, nullable %v, from %q", written, resolved.Name, resolved.Nullable, resolved.Origin)
			}
		})
	}
}

func TestATypeTheEngineWorkedOutItselfSaysSo(t *testing.T) {
	codebase := phpSource(t, `<?php

namespace Shop;

final class Slip {}

final class Desk
{
    public function make()
    {
        $slip = new Slip();

        return $slip;
    }
}
`)

	resolved := resolvedAt(t, codebase, "new Slip()").Node().Resolved
	if resolved == nil {
		t.Fatal("a construction carries no resolved type")
	}

	if resolved.Name != "Shop\\Slip" || resolved.Origin != "inferred" {
		t.Errorf("a construction resolves to %q, from %q", resolved.Name, resolved.Origin)
	}
}

func TestAValueTheTypeEngineCannotReadCarriesNoType(t *testing.T) {
	codebase := phpSource(t, `<?php

namespace Shop;

final class Counter
{
    public function count($rows)
    {
        $total = $rows;

        return $total;
    }
}
`)

	for _, written := range []string{"$rows", "$total"} {
		if resolved := resolvedAt(t, codebase, written).Node().Resolved; resolved != nil {
			t.Errorf("%s carries the type %q, and nothing says what it holds", written, resolved.Text)
		}
	}
}
