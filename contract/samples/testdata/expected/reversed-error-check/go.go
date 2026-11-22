package files // @contract role/module/declarations -> [openFn]

import "os"

func Open(name string) error { // @label openFn function
	_, err := os.Open(name)
	if nil != err {
		return err
	}
	return nil
}
