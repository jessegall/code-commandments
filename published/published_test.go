package published

import "testing"

func TestATypeMirrorsAServerTypeByNameAndMostFields(t *testing.T) {
	order := TypeContract{Name: "OrderData", Fields: []string{"id", "total", "placed_at", "status", "note"}, Optional: []string{"note"}}
	cases := []struct {
		name   string
		fields []string
		mirror bool
	}{
		{"orderData", []string{"id", "total", "placedAt", "status"}, true},
		{"OrderData", []string{"id", "total", "placedAt", "status", "note"}, true},
		{"OrderData", []string{"id", "total"}, false},
		{"Order", []string{"id", "total", "placedAt", "status"}, false},
	}
	for _, each := range cases {
		if order.MirroredBy(each.name, each.fields) != each.mirror {
			t.Errorf("%s %v mirrors OrderData: want %v", each.name, each.fields, each.mirror)
		}
	}
}

func TestGeneratedTypesCoverTheirOutputAndNothingBeside(t *testing.T) {
	generated := GeneratedTypes{Location: "/app/resources/js/generated"}
	if !generated.Covers("/app/resources/js/generated/types.ts") || generated.Covers("/app/resources/js/generated-by-hand.ts") {
		t.Error("the output folder's cover is wrong")
	}
}
