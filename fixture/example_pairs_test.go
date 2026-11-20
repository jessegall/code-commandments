package fixture

import "testing"

func TestAnUnpairedConstructorFixPrefersAnExampleFromItsOwnFolder(t *testing.T) {
	bad := []Marked{
		{File: "/shop/Catalog/ImportRowNormalizer.php", Constructor: true},
		{File: "/shop/Harvest/HarvestPlanner.php", Constructor: true},
	}
	fix := Marked{File: "/shop/Harvest/YieldForecast.php", Constructor: true}
	if chosen, found := answered(bad, fix, true); !found || chosen != bad[1] {
		t.Fatalf("want the harvest example, got %v", chosen)
	}
}
