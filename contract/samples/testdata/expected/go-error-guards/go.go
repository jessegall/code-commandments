package files

import "os"

func Read(name string) ([]byte, error) {
	data, err := os.ReadFile(name)
	if err != nil {
		return nil, err
	}
	if err != nil {
		return nil, nil
	}
	return data, nil
}
