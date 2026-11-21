package shop

import "errors"

func importBins(codes []string, read func(string) error) int {
	loaded := 0
	for _, code := range codes {
		// @righteous SwallowCatch
		if err := read(code); err != nil {
			continue
		}
		loaded++
	}

	return loaded
}

func receiveBin(inbound <-chan string, read func(string) error, inventory map[string]bool) (bool, error) {
	code, open := <-inbound
	if !open {
		return false, nil
	}
	if inventory[code] {
		return true, nil
	}
	if err := read(code); err != nil {
		// @sin WrappingWithoutCause
		return false, errors.New("could not read the bin")
	}
	inventory[code] = true
	return true, nil
}

// @fixed SwallowCatch
func receiveAllBins(codes []string, read func(string) error) error {
	for _, code := range codes {
		if err := read(code); err != nil {
			return err
		}
	}

	return nil
}
