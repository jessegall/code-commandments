package core

// @sin NamespaceDependency
import "commandments.fixture/shop/layers/payroll/view"

func Caption(employee string, masks map[string]string) string {
	if masked, found := masks[employee]; found {
		return masked
	}
	return view.Caption + ": " + employee
}
