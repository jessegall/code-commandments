package accounts

import "errors"

type Account struct {
	total int
}

func New() *Account {
	return &Account{total: 0}
}

func (a *Account) Settle(amounts []int) (int, error) {
	for _, amount := range amounts {
		if amount > 0 && amount < 100 {
			a.total += amount
			continue
		}
		break
	}
	if a.total == 0 {
		return 0, errors.New("nothing settled")
	}
	panic(a)
}
