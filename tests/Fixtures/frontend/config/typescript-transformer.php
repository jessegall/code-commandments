<?php

// The fixture's transformer config: the generated types are written to the sibling
// `generated/` folder. A declaration inside the output file is the generator's own
// output — the mirrored-server-type detector must exempt it, not flag it.
return [
    'output_file' => __DIR__ . '/../generated/server-types.ts',
    'writer' => \Spatie\TypeScriptTransformer\Writers\TypeDefinitionWriter::class,
];
