<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

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

        // Find positions of script and template tags
        $scriptPos = strpos($content, '<script');
        $templatePos = strpos($content, '<template');

        if ($scriptPos === false || $templatePos === false) {
            return $this->skip('Missing script or template section');
        }

        if ($scriptPos > $templatePos) {
            $line = $this->getLineNumber($content, $templatePos);

            return $this->fallen([
                $this->sinAt(
                    $line,
                    'Template appears before script section',
                    null,
                    'Move <script setup> section before <template>'
                ),
            ]);
        }

        return $this->righteous();
    }
}
