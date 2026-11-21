package files

func Check(err error) error {
	if err != nil {
		return err
	}
	return nil
}
