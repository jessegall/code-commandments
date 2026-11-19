package shop

import "errors"

// Return is a parcel a customer sends back.
type Return struct {
	Reason string
	Cents  int
	Opened bool
}

// RefundShare says how much of the price a return earns back, rung by rung on the reason.
func RefundShare(item Return) int {
	// @sin SubjectLadder
	if item.Reason == "damaged" {
		return 100
	} else if item.Reason == "late" {
		return 80
	} else if item.Reason == "wrong-size" {
		return 60
	} else if item.Reason == "changed-mind" {
		return 40
	}

	return 0
}

// @fixed SubjectLadder

// RefundShareBySwitch answers the refund share with one switch over the reason.
func RefundShareBySwitch(item Return) int {
	switch item.Reason {
	case "damaged":
		return 100
	case "late":
		return 80
	case "wrong-size":
		return 60
	case "changed-mind":
		return 40
	}

	return 0
}

// Restock puts an unopened return back on the shelf and logs either way.
func Restock(item Return, shelf map[string]int, log []string) []string {
	// @sin DuplicatedBranchTail
	if item.Opened {
		shelf["seconds"]++
		log = append(log, item.Reason)
	} else {
		shelf["new"]++
		log = append(log, item.Reason)
	}

	return log
}

// Refund pays a return out, refusing a parcel with nothing to pay.
func Refund(item Return) (int, error) {
	if item.Cents <= 0 {
		return 0, errors.New("nothing to refund")
		// @sin UnreachableStatement
		item.Cents = 0
	}

	return item.Cents, nil
}

// @righteous SubjectLadder

// Triage tests a different fact at each rung, so the chain is no dispatch over one subject.
func Triage(item Return) string {
	if item.Reason == "damaged" {
		return "inspect"
	} else if item.Cents > 5000 {
		return "approve"
	} else if item.Opened {
		return "seconds"
	}

	return "shelf"
}
