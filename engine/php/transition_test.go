package php_test

import (
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func TestSharedGuardBesideTransitionRecognizesPHPStatements(t *testing.T) {
	codebase := phpSource(t, `<?php
namespace App;

enum State { case Open; case Closed; }

function guarded(Order $order): void {
    if (State::Open !== $order->state && $order->state !== State::Closed) {
        throw new Refusal();
    }
    note();
    $order->close();
}

function alternative(Order $order): void {
    if ($order->state === State::Closed) {
        throw new Refusal();
    } else {
    }
    $order->close();
}

function differentReceiver(Order $first, Order $second): void {
    if ($first->state === State::Closed || $second->state === State::Closed) {
        throw new Refusal();
    }
    $first->close();
}

function orderedComparison(Order $order): void {
    if ($order->state < State::Closed) {
        throw new Refusal();
    }
    $order->close();
}
`)
	var actual []string
	for _, guard := range codebase.WhereIs(engine.Branch).Where(engine.Match.GuardsAnotherObjectsTransition).Get() {
		actual = append(actual, guard.EnclosingFunction().Name())
	}
	if want := []string{"guarded"}; !slices.Equal(actual, want) {
		t.Fatalf("matched %v, want %v", actual, want)
	}
}
