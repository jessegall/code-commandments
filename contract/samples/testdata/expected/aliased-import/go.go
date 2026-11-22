package billing

// @contract kind/import
// @contract role/import/alias -> alias
import money "math/big" // @label alias binding

func Zero() *money.Int {
	return money.NewInt(0)
}
