package backend

import (
	"strings"
	"testing"

	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
)

var redundantEnumUnwrap = scribeCase{spatie.RedundantEnumUnwrapDetector{}, RedundantEnumUnwrapScribe{}}

// The scribe case PHP holds in RedundantEnumUnwrapDetectorTest.
func TestRedundantEnumUnwrapDropsTheValueUnwrap(t *testing.T) {
	source := `<?php
namespace App;
use Spatie\LaravelData\Data;
enum Status: string { case Draft = 'draft'; case Live = 'live'; }
enum Priority: int { case Low = 1; case High = 2; }
class Order { public function __construct(public Status $status, public Priority $priority) {} }class OrderData extends Data { public function __construct(public Status $status) {} }
class Factory {
    public function make(Order $order): OrderData {
        return OrderData::from(['status' => $order->status->value]);
    }
}`

	fixed := redundantEnumUnwrap.fix(t, source)
	if !strings.Contains(fixed, "'status' => $order->status]") || strings.Contains(fixed, "->status->value") {
		t.Fatalf("got\n%s", fixed)
	}
}
