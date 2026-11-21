package python_test

import (

)

func without(read, part string) string {
	for at := 0; at+len(part) <= len(read); at++ {
		if read[at:at+len(part)] == part {
			return read[:at] + read[at+len(part):]
		}
	}

	return read
}

func has(read, part string) bool {
	return without(read, part) != read
}
