package billing

import money "math/big"

func Zero() *money.Int {
	return money.NewInt(0)
}
