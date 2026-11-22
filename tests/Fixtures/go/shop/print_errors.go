package shop

import "fmt"

func printShelfLabel(labels <-chan string, print func(string) error) (*string, error) {
	label, open := <-labels
	if !open {
		return nil, nil
	}
	// @sin SwallowCatch
	if err := print(label); err != nil {
		return nil, nil
	}
	return &label, nil
}

// @sin NearDuplicateFunction
func printForOrder(order string, print func() error) error {
	if err := print(); err != nil {
		// @sin WrappingWithoutCause
		return fmt.Errorf("order %s label: %v", order, err)
	}

	return nil
}

// @fixed WrappingWithoutCause
// @sin NearDuplicateFunction
func printWithCause(order string, print func() error) error {
	if err := print(); err != nil {
		return fmt.Errorf("order %s label: %w", order, err)
	}

	return nil
}
