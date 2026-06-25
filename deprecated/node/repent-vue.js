#!/usr/bin/env node

/**
 * Vue SFC auto-fixer for code-commandments.
 *
 * This script uses @vue/compiler-sfc and @babel/parser for AST-based
 * transformations of Vue Single File Components.
 *
 * Usage:
 *   node repent-vue.js <file> <transformation>
 *
 * Transformations:
 *   - reorder-sections: Ensure script comes before template
 *   - add-script-setup: Convert Options API to Composition API (basic)
 */

import fs from 'fs';
import { parse as parseSFC, compileScript } from '@vue/compiler-sfc';
import * as parser from '@babel/parser';
import traverse from '@babel/traverse';
import generate from '@babel/generator';
import * as t from '@babel/types';

const [,, filePath, transformation] = process.argv;

if (!filePath || !transformation) {
  console.error('Usage: node repent-vue.js <file> <transformation>');
  console.error('Transformations: reorder-sections, add-script-setup');
  process.exit(1);
}

const content = fs.readFileSync(filePath, 'utf-8');
const { descriptor, errors } = parseSFC(content);

if (errors.length > 0) {
  console.error('Parse errors:', errors);
  process.exit(1);
}

/**
 * Reorder SFC sections to: script, template, style
 */
function reorderSections() {
  const sections = [];

  // Add script first
  if (descriptor.script) {
    const scriptAttrs = buildAttrs(descriptor.script);
    sections.push(`<script${scriptAttrs}>\n${descriptor.script.content}</script>`);
  }
  if (descriptor.scriptSetup) {
    const setupAttrs = buildAttrs(descriptor.scriptSetup);
    sections.push(`<script setup${setupAttrs}>\n${descriptor.scriptSetup.content}</script>`);
  }

  // Add template second
  if (descriptor.template) {
    const templateAttrs = buildAttrs(descriptor.template);
    sections.push(`<template${templateAttrs}>\n${descriptor.template.content}</template>`);
  }

  // Add styles last
  for (const style of descriptor.styles) {
    const styleAttrs = buildAttrs(style);
    sections.push(`<style${styleAttrs}>\n${style.content}</style>`);
  }

  return sections.join('\n\n') + '\n';
}

/**
 * Build attribute string from descriptor block
 */
function buildAttrs(block) {
  const attrs = [];

  if (block.lang) {
    attrs.push(`lang="${block.lang}"`);
  }
  if (block.scoped) {
    attrs.push('scoped');
  }

  return attrs.length > 0 ? ' ' + attrs.join(' ') : '';
}

/**
 * Basic Options API to Composition API conversion
 * (This is a simplified version - real conversion is complex)
 */
function addScriptSetup() {
  if (descriptor.scriptSetup) {
    // Already using script setup
    return content;
  }

  if (!descriptor.script) {
    return content;
  }

  const scriptContent = descriptor.script.content;

  // Parse the script content
  const ast = parser.parse(scriptContent, {
    sourceType: 'module',
    plugins: ['typescript', 'decorators-legacy'],
  });

  // Find export default
  let exportDefault = null;
  traverse.default(ast, {
    ExportDefaultDeclaration(path) {
      exportDefault = path.node.declaration;
    },
  });

  if (!exportDefault || !t.isObjectExpression(exportDefault)) {
    console.error('Could not find export default object');
    return content;
  }

  // Extract data, methods, computed, etc.
  const imports = new Set(['defineComponent']);
  const setupBody = [];

  for (const prop of exportDefault.properties) {
    if (!t.isObjectProperty(prop) && !t.isObjectMethod(prop)) continue;

    const key = t.isIdentifier(prop.key) ? prop.key.name : null;

    if (key === 'data' && t.isObjectMethod(prop)) {
      // Convert data() to refs
      imports.add('ref');
      const returnStmt = prop.body.body.find(s => t.isReturnStatement(s));
      if (returnStmt && t.isObjectExpression(returnStmt.argument)) {
        for (const dataProp of returnStmt.argument.properties) {
          if (t.isObjectProperty(dataProp) && t.isIdentifier(dataProp.key)) {
            const { code } = generate.default(dataProp.value);
            setupBody.push(`const ${dataProp.key.name} = ref(${code})`);
          }
        }
      }
    }

    if (key === 'methods' && t.isObjectProperty(prop) && t.isObjectExpression(prop.value)) {
      // Convert methods to functions
      for (const method of prop.value.properties) {
        if (t.isObjectMethod(method) && t.isIdentifier(method.key)) {
          const { code } = generate.default(method.body);
          const params = method.params.map(p => generate.default(p).code).join(', ');
          setupBody.push(`const ${method.key.name} = (${params}) => ${code}`);
        }
      }
    }
  }

  // Generate new script setup
  const importsStr = `import { ${[...imports].join(', ')} } from 'vue'`;
  const newScript = `${importsStr}\n\n${setupBody.join('\n')}`;

  // Rebuild the SFC
  const sections = [];
  const lang = descriptor.script.lang ? ` lang="${descriptor.script.lang}"` : '';
  sections.push(`<script setup${lang}>\n${newScript}\n</script>`);

  if (descriptor.template) {
    const templateAttrs = buildAttrs(descriptor.template);
    sections.push(`<template${templateAttrs}>${descriptor.template.content}</template>`);
  }

  for (const style of descriptor.styles) {
    const styleAttrs = buildAttrs(style);
    sections.push(`<style${styleAttrs}>${style.content}</style>`);
  }

  return sections.join('\n\n') + '\n';
}

// Execute transformation
let result;
switch (transformation) {
  case 'reorder-sections':
    result = reorderSections();
    break;
  case 'add-script-setup':
    result = addScriptSetup();
    break;
  default:
    console.error(`Unknown transformation: ${transformation}`);
    process.exit(1);
}

// Output result to stdout
console.log(result);
