package shop

type RebateArgumentCode int64

type RebateArgument struct {
	amount int64
	issued bool
	paid   bool
}

func rebateLabel(amount int64) int64 {
	return amount / 100
}

func rebateLetThrough(amount RebateArgumentCode, floor int64) bool {
	// @sin ConvertedArgument
	return rebateLabel(int64(amount)) >= floor
}

func rebateChange(previous RebateArgumentCode, current RebateArgumentCode) int64 {
	delta := current - previous
	// @sin ConvertedArgument
	return rebateLabel(int64(delta))
}

func rebateAllowance(amount int64, granted bool, paid bool) int64 {
	if granted && !paid {
		return amount / 2
	}
	return 0
}

func rebatesPayable(claims map[string]RebateArgument) map[string]int64 {
	payable := make(map[string]int64, len(claims))
	for key, claim := range claims {
		// @sin DerivedArgument
		// @sin ComputedBooleanArgument
		payable[key] = rebateAllowance(claim.amount, claim.issued, claim.paid)
	}
	return payable
}

func latestRebate(claim *RebateArgument) int64 {
	if claim == nil {
		return 0
	}
	// @sin DerivedArgument
	// @sin ComputedBooleanArgument
	return rebateAllowance(claim.amount, claim.issued, claim.paid)
}

func (r RebateArgument) HasCredit() bool { return r.issued }
func (r RebateArgument) WasPaid() bool   { return r.paid }

func creditStillDue(issued bool, paid bool) bool {
	return issued && !paid
}

func canDismissRebate(claim RebateArgument) bool {
	// @sin ComputedBooleanArgument
	return !creditStillDue(claim.HasCredit(), claim.WasPaid())
}

func preserveOutstandingRebates(claims []RebateArgument) []RebateArgument {
	open := make([]RebateArgument, 0, len(claims))
	for _, claim := range claims {
		// @sin ComputedBooleanArgument
		if creditStillDue(claim.HasCredit(), claim.WasPaid()) {
			open = append(open, claim)
		}
	}
	return open
}

// @sin FlagArgument
func onRebateApproved(approved bool, publish func(string)) {
	if approved {
		publish("credit issued")
	} else {
		publish("credit revoked")
	}
}

// @sin FilledParameter
func recomputeRebate(claim *RebateArgument, eligible int64) {
	if eligible > 0 {
		claim.amount = eligible / 10
	}
}

func dueAfterRebate(total int64, claim *RebateArgument) int64 {
	recomputeRebate(claim, total)
	return total - claim.amount
}

// @sin ParamResolvedFromParam
func appliedRebate(claims map[RebateArgumentCode]RebateArgument, code RebateArgumentCode) int64 {
	claim, found := claims[code]
	if !found || !claim.paid {
		return 0
	}
	return claim.amount
}

func outstandingRebates(
	claims map[string]RebateArgument,
	// @sin UnusedParameter
	includePaid bool,
) map[string]int64 {
	outstanding := map[string]int64{}
	for code, claim := range claims {
		if claim.issued && !claim.paid {
			outstanding[code] = claim.amount
		}
	}
	return outstanding
}
