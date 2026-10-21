package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// TestAConditionIsAConceptOnlyWhenItAsksSeveralObjects holds the shared reading of an unnamed condition to what
// it claims: parts alone are no sin, and a chain over one subject is a range check. The reading lives in the
// engine and every language reaches it the same way, so Python stands for all of them here.
func TestAConditionIsAConceptOnlyWhenItAsksSeveralObjects(t *testing.T) {
	for name, test := range map[string]struct {
		source string
		want   int
	}{
		"three parts over three objects": {
			"def tick(event, slot, worker) -> None:\n    if event.late and slot.open and worker.idle:\n        worker.take(slot)\n",
			1,
		},
		"four parts over its own field and two objects": {
			"class Clock:\n    def on_ticked(self, event, nxt) -> None:\n        if self.auto and not self.said and event.at > self.since and nxt is not None:\n            self.say(nxt)\n",
			1,
		},
		"a chain of calls over two objects": {
			"def ship(order, slot) -> None:\n    if order.is_paid() and order.has_lines() and slot.free():\n        order.ship(slot)\n",
			1,
		},
		"two parts only": {
			"def tick(event, slot) -> None:\n    if event.late and slot.open:\n        slot.close()\n",
			0,
		},
		"three parts over one object": {
			"def within(window, at) -> bool:\n    if window.start <= at and window.end >= at and window.closed is False:\n        return True\n    return False\n",
			0,
		},
		"a range check over one local": {
			"def within(at, start, end) -> bool:\n    if start <= at and at <= end and at != 0:\n        return True\n    return False\n",
			0,
		},
		"a guard standing at the top": {
			"def ship(order, slot, worker) -> None:\n    if order.paid is None or slot.open is None or worker.idle is None:\n        return\n    worker.take(slot)\n",
			0,
		},
		"a chain the function returns": {
			"def shippable(order, slot, worker) -> bool:\n    return order.paid and slot.open and worker.idle\n",
			0,
		},
		"a constant read off a class is no object": {
			"class Mode:\n    AUTO = 'auto'\n\n\ndef tick(mode, at) -> None:\n    if mode == Mode.AUTO and at > 0 and at < 10:\n        print(at)\n",
			0,
		},
	} {
		t.Run(name, func(t *testing.T) {
			codebase := pythontest.FromSource(t, map[string]string{"app/thing.py": test.source})
			found := python.In(codebase).
				WhereIs(engine.Conjunction).
				Where(engine.TestsAnUnnamedConcept).
				Count()

			if found != test.want {
				t.Errorf("found %d, want %d", found, test.want)
			}
		})
	}
}
