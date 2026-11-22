package php

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine/php/shop"
)

func TestTheCommittedShopIsGeneratedFromTodaysSources(t *testing.T) {
	committed, err := os.ReadFile(filepath.Join(shop.Testdata(), "shop.digest"))
	if err != nil {
		t.Fatal(err)
	}
	digest, err := shop.Digest()
	if err != nil {
		t.Fatal(err)
	}
	if strings.TrimSpace(string(committed)) != digest {
		t.Fatal("a source engine/php/testdata is generated from changed since it was generated (the shop fixture, the PHP engine, composer.json, the bridge or the oracle): run go run ./engine/php/shop/generate")
	}
}
