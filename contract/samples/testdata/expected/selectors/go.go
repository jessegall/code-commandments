package accounts

import "errors"

type Account struct {
	total int
}

func New() *Account {
	return &Account{total: 0}
}

func (a *Account) Settle(amounts []int) (int, error) {
	// @contract kind/loop
	// @contract fact/loop/form = "for-each"
	for _, amount := range amounts { // @label each loop
		// @contract role/if/condition -> both
		if amount > 0 && amount < 100 { // @label both logical
			a.total += amount // @contract kind/assignment
			continue // @contract link/continue/destination -> each
		}
		break // @contract link/break/destination -> each
	}
	if a.total == 0 {
		return 0, errors.New("nothing settled")
	}
	panic(a)
}
