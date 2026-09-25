// Bundles the bridge into dist/bridge.mjs, one file needing only node, with TypeScript's standard library beside it
// and Vue's type declarations under dist/types, for a project whose own are not installed.
import { build } from 'esbuild'
import { copyFileSync, mkdirSync, readdirSync, rmSync } from 'node:fs'
import { dirname } from 'node:path'

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

/** The Vue packages whose declarations type a ref, each with the files its package.json points at. */
const VUE_TYPES = {
    vue: ['package.json', 'dist/vue.d.ts', 'dist/vue.d.mts'],
    '@vue/runtime-dom': ['package.json', 'dist/runtime-dom.d.ts'],
    '@vue/runtime-core': ['package.json', 'dist/runtime-core.d.ts'],
    '@vue/reactivity': ['package.json', 'dist/reactivity.d.ts'],
    '@vue/shared': ['package.json', 'dist/shared.d.ts'],
}
for (const [name, files] of Object.entries(VUE_TYPES)) {
    for (const file of files) {
        const target = `dist/types/node_modules/${name}/${file}`
        mkdirSync(dirname(target), { recursive: true })
        copyFileSync(`node_modules/${name}/${file}`, target)
    }
}
