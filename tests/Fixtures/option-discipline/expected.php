<?php

// The EXACT set of OptionDiscipline verdicts the fake project must produce — no
// more (a false positive / the old contradiction returning), no fewer (a verdict
// going silent). Signature: "<relative src path>|<case tag>" where A = adopt
// (decides-null), B = never-none, D = wrap-then-unwrap.
return [
    'Adopt/UserDirectory.php|A',
    'NeverNone/Clock.php|B',
    'WrapUnwrap/Greeter.php|D',
];
