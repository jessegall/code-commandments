package shop

const (
	Bronze = iota
	Silver
	Gold
	Platinum
)

// Member is a customer collecting points.
type Member struct {
	Tier   int
	Points int
}

// Bonus multiplies a member's points by the tier they hold.
func Bonus(member Member) int {
	// @sin SubjectLadder
	if Bronze == member.Tier {
		return member.Points
	} else if Silver == member.Tier {
		return member.Points * 2
	} else if Gold == member.Tier {
		return member.Points * 3
	} else if Platinum == member.Tier {
		return member.Points * 5
	}
	panic("a member holds no tier")
}

// Redeem spends points, or says why it cannot.
func Redeem(member Member, cost int) (Member, string) {
	// @sin DuplicatedBranchTail
	// @sin RedundantElse
	if member.Points < cost {
		return member, "too few points"
	} else {
		member.Points -= cost
		return member, "too few points"
	}
}

// Expire clears a member's points once they lapse.
func Expire(member Member, lapsed bool) Member {
	if !lapsed {
		return member
	}
	member.Points = 0
	panic("expiry is not run by hand")
	// @sin UnreachableStatement
	member.Tier = Bronze

	return member
}

// @fixed UnreachableStatement

// ExpireLapsed clears a lapsed member's points with nothing written after it leaves.
func ExpireLapsed(member Member, lapsed bool) Member {
	if !lapsed {
		return member
	}
	member.Points = 0

	return member
}

// @righteous UnreachableStatement

// Settle jumps to a label below its return, so the code after the return still runs.
func Settle(member Member) Member {
	if member.Points < 0 {
		goto reset
	}

	return member
reset:
	member.Points = 0

	return member
}

func pointsFor(spent []int, bonus int) int {
	for _, amount := range spent {
		// @sin ReassignedParameter
		bonus += amount / 10
	}

	return bonus
}

func codePrefixes(code string, known map[string]int) int {
	matched := 0
	// @sin NonCountingFor
	for prefix := code; prefix != ""; prefix = prefix[:len(prefix)-1] {
		matched += known[prefix]
	}

	return matched
}
