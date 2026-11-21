package python_test

import (
	"encoding/json"

)

func marshal(value any) string {
	raw, _ := json.Marshal(value)

	return string(raw)
}
