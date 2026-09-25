// Bundles the bridge into dist/bridge.mjs, one file needing only node, with TypeScript's standard library beside it.
import { build } from 'esbuild'
import { copyFileSync, mkdirSync, readdirSync, rmSync } from 'node:fs'

rmSync('dist', { recursive: true, force: true })
mkdirSync('dist')
await build({
    entryPoints: ['src/main.mjs'],
    bundle: true,
    platform: 'node',
    format: 'esm',
    target: 'node18',
    minify: true,
    legalComments: 'eof',
    outfile: 'dist/bridge.mjs',
    logLevel: 'error',
    banner: {
        js: [
            "import { createRequire } from 'node:module';",
            "import { fileURLToPath } from 'node:url';",
            "import { dirname } from 'node:path';",
            'const require = createRequire(import.meta.url);',
            'const __filename = fileURLToPath(import.meta.url);',
            'const __dirname = dirname(__filename);',
        ].join(''),
    },
})
for (const name of readdirSync('node_modules/typescript/lib').filter((name) => /^lib\..*\.d\.ts$/.test(name))) {
    copyFileSync(`node_modules/typescript/lib/${name}`, `dist/${name}`)
}
