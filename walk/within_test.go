package walk

import "testing"

// A path is within a folder when it is the folder or lies under it, never when it only starts with the folder's name.
func TestAPathIsWithinItsFolderAndNotWithinAFolderOfALongerName(t *testing.T) {
	for _, each := range []struct {
		folder, path string
		within       bool
	}{
		{"/work/shop", "/work/shop", true},
		{"/work/shop", "/work/shop/cart.py", true},
		{"/work/shop/", "/work/shop/cart.py", true},
		{"/work/shop", "/work/shopping/cart.py", false},
		{"/work/shop", "/work", false},
	} {
		if got := Within(each.folder, each.path); got != each.within {
			t.Errorf("Within(%q, %q) = %v", each.folder, each.path, got)
		}
	}
}
