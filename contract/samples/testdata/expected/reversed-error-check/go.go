package files

import "os"

func Open(name string) error {
	_, err := os.Open(name)
	if nil != err {
		return err
	}
	return nil
}
