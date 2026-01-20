#!/usr/bin/env node

/**
 * TypeScript auto-fixer for code-commandments.
 *
 * This script uses @babel/parser for AST-based transformations
 * of TypeScript files.
 *
 * Usage:
 *   node repent-ts.js <file> <transformation>
 *
 * Transformations:
 *   - arrow-functions: Convert function expressions to arrow functions
 *   - const-functions: Convert let/var function assignments to const
 */

import fs from 'fs';
import * as parser from '@babel/parser';
import traverse from '@babel/traverse';
import generate from '@babel/generator';
import * as t from '@babel/types';

const [,, filePath, transformation] = process.argv;

if (!filePath || !transformation) {
  console.error('Usage: node repent-ts.js <file> <transformation>');
  console.error('Transformations: arrow-functions, const-functions');
  process.exit(1);
}

const content = fs.readFileSync(filePath, 'utf-8');

const ast = parser.parse(content, {
  sourceType: 'module',
  plugins: ['typescript', 'jsx', 'decorators-legacy'],
});

/**
 * Convert function expressions to arrow functions
 */
function convertToArrowFunctions() {
  traverse.default(ast, {
    VariableDeclaration(path) {
      for (const declarator of path.node.declarations) {
        if (t.isFunctionExpression(declarator.init)) {
          const func = declarator.init;

          // Create arrow function
          const arrowFunc = t.arrowFunctionExpression(
            func.params,
            func.body,
            func.async
          );

          // Copy type annotations if present
          if (func.returnType) {
            arrowFunc.returnType = func.returnType;
          }

          declarator.init = arrowFunc;
        }
      }
    },
  });

  return generate.default(ast, { retainLines: true }).code;
}

/**
 * Convert let/var function assignments to const
 */
function convertToConstFunctions() {
  traverse.default(ast, {
    VariableDeclaration(path) {
      const { kind, declarations } = path.node;

      if (kind === 'let' || kind === 'var') {
        // Check if all declarators are functions
        const allFunctions = declarations.every(
          d => t.isFunctionExpression(d.init) || t.isArrowFunctionExpression(d.init)
        );

        if (allFunctions) {
          path.node.kind = 'const';
        }
      }
    },
  });

  return generate.default(ast, { retainLines: true }).code;
}

// Execute transformation
let result;
switch (transformation) {
  case 'arrow-functions':
    result = convertToArrowFunctions();
    break;
  case 'const-functions':
    result = convertToConstFunctions();
    break;
  default:
    console.error(`Unknown transformation: ${transformation}`);
    process.exit(1);
}

// Output result to stdout
console.log(result);
