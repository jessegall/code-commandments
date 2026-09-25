package parity

import "testing"

func TestAConfigJSONIsTheGoldensConfigPHPOnlyWhenTheSettingsAgree(t *testing.T) {
	php := "=== created .commandments/config.php\n<?php\nreturn function ($config) {\n    $config->paths('src');\n};\n\n"
	want := Result{Stdout: "written to .commandments/config.php", Files: php}

	for json, equal := range map[string]bool{
		`{"paths": ["src"]}`: true,
		`{"paths": ["app"]}`: false,
	} {
		got := Result{Stdout: "written to .commandments/config.json", Files: "=== created .commandments/config.json\n" + json + "\n\n"}
		wanted, gotten := EquateConfigs(want, got, t.TempDir())

		if (wanted == gotten) != equal {
			t.Errorf("%s: equated %v\n%+v\n%+v", json, wanted == gotten, wanted, gotten)
		}
	}
}
