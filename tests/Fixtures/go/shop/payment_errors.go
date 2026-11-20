package shop

import "fmt"

func auditPayment(read func() error, record func()) {
	// @sin SwallowCatch
	if err := read(); err != nil {
	}
	record()
}

func closePayment(payments []int, close func(int) error) (int, error) {
	total := 0
	for _, cents := range payments {
		err := close(cents)
		if err != nil {
			// @sin WrappingWithoutCause
			return total, fmt.Errorf("payment of %d cents failed: %s", cents, err)
		}
		total += cents
	}
	return total, nil
}

// @righteous SwallowCatch
func auditPaymentOrReturn(read func() error) error {
	if err := read(); err != nil {
		return err
	}

	return nil
}
