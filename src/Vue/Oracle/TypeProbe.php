<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Oracle;

use JesseGall\CodeCommandments\Vue\Block;
use JesseGall\CodeCommandments\Vue\Sfc;

/**
 * A copy of a component with a type-error PROBE appended inside `<script setup>` for each name we
 * want typed. Each probe assigns the local to an impossible, name-encoding type
 * (`const __cc_x: __CcNo_x = x;`), so a checker reports `x`'s RESOLVED type in the assignability
 * error, and the target type's name (`__CcNo_x`) says which local it is. Probing the real `.vue`
 * means macros, auto-imports, and tsconfig paths all resolve exactly as they do at build time — no
 * `<script setup>` reconstruction. The inverse read is {@see TscDiagnostics}.
 */
final class TypeProbe
{
    /** The probe target-type prefix — the protocol {@see TscDiagnostics} decodes a name out of. */
    public const string MARKER = '__CcNo_';

    /**
     * @param  list<string>  $names
     */
    public function __construct(
        private readonly Sfc $component,
        private readonly array $names,
    ) {}

    /**
     * The component's source with the probes spliced in at the end of `<script setup>` (just
     * before `</script>`, where the locals are in scope). Null when there is no setup block to
     * host them, or nothing to probe.
     */
    public function source(): ?string
    {
        $setup = $this->setupBlock();

        if ($setup === null || $this->names === []) {
            return null;
        }

        $at = $setup->start + strlen($setup->content);

        return substr($this->component->source, 0, $at)
            . $this->probes()
            . substr($this->component->source, $at);
    }

    private function probes(): string
    {
        $out = "\n";

        foreach ($this->names as $name) {
            $type = self::MARKER . $name;
            // A `string &`-branded impossible type: NOTHING is assignable to it, and — crucially —
            // every source (object, array, primitive, union) fails as a uniform TS2322 "not
            // assignable" carrying the resolved type, never a shape-specific "missing property"
            // (TS2741) that an object target would provoke.
            $out .= "type {$type} = string & { readonly __ccBrand: '{$name}' };\n";
            $out .= "const __cc_{$name}: {$type} = {$name};\n";
        }

        return $out;
    }

    private function setupBlock(): ?Block
    {
        foreach ($this->component->blocks as $block) {
            if ($block->tag === 'script' && $block->hasAttribute('setup')) {
                return $block;
            }
        }

        return null;
    }
}
