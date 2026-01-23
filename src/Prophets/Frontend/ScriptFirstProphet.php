<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VueContext;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;
use JesseGall\CodeCommandments\Support\TextHelper;

/**
 * Thou shalt put script before template in Vue files.
 *
 * Vue SFC order should be: script, template, style.
 */
class ScriptFirstProphet extends FrontendCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Thou shalt put script before template';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Vue Single File Components should follow a consistent ordering:
1. <script> (or <script setup>)
2. <template>
3. <style>

This order is recommended because:
- Logic (script) defines what the component does
- Template shows how it renders
- Style is supplementary

Forbidden:
```vue
<template>...</template>
<script>...</script>
<style>...</style>
```

Required:
```vue
<script setup lang="ts">...</script>
<template>...</template>
<style scoped>...</style>
```
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        return VuePipeline::make($filePath, $content)
            ->mapToSins(fn (VueContext $ctx) => $this->checkScriptOrder($ctx))
            ->judge();
    }

    private function checkScriptOrder(VueContext $ctx): ?Sin
    {
        $scriptPos = strpos($ctx->content, '<script');
        $templatePos = strpos($ctx->content, '<template');

        if ($scriptPos === false || $templatePos === false) {
            return null;
        }

        if ($scriptPos > $templatePos) {
            $line = TextHelper::getLineNumber($ctx->content, $templatePos);

            return Sin::at(
                line: $line,
                message: 'Template appears before script section',
                suggestion: 'Move <script setup> section before <template>'
            );
        }

        return null;
    }
}
