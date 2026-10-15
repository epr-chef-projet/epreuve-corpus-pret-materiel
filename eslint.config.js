import js from '@eslint/js';
import { defineConfig, globalIgnores } from 'eslint/config';
import prettier from 'eslint-config-prettier/flat';
import vue from 'eslint-plugin-vue';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default defineConfig(
    globalIgnores([
        '**/node_modules/**',
        'vendor/**',
        'public/build/**',
        'public/browser-build/**',
        'bootstrap/ssr/**',
        'bootstrap/cache/**',
        'storage/**',
        'coverage/**',
        'test-results/**',
        'playwright-report/**',
        'resources/js/actions/**',
        'resources/js/routes/**',
        'resources/js/wayfinder/**',
        'tests/Frontend/generated/**',
        'resources/js/types/generated/**',
        '.ai/rules/**',
        '.claude/rules/**',
        '.idea/**',
        '.epr-starter-source/**',
        '.epr-local/**',
        'project-management/local/**',
        '.vitest/**',
    ]),
    js.configs.recommended,
    {
        files: ['**/*.{ts,tsx,vue}'],
        extends: [tseslint.configs.recommendedTypeChecked],
        languageOptions: {
            parserOptions: {
                projectService: true,
                tsconfigRootDir: import.meta.dirname,
                extraFileExtensions: ['.vue'],
            },
        },
    },
    {
        files: ['**/*.vue'],
        extends: [vue.configs['flat/recommended']],
        languageOptions: {
            parserOptions: {
                parser: tseslint.parser,
            },
        },
        rules: tseslint.configs.eslintRecommended.rules,
    },
    {
        files: ['resources/js/**/*.{js,ts,tsx,vue}'],
        languageOptions: {
            globals: globals.browser,
        },
    },
    {
        files: [
            'vite.config.ts',
            'vitest.config.ts',
            'playwright.config.ts',
            'tests/Browser/**/*.ts',
        ],
        languageOptions: {
            parserOptions: {
                projectService: false,
                project: './tsconfig.node.json',
            },
        },
    },
    {
        files: [
            'eslint.config.js',
            'prettier.config.js',
            'vite.config.ts',
            'vitest.config.ts',
            'playwright.config.ts',
            'scripts/**/*.mjs',
        ],
        languageOptions: {
            globals: globals.nodeBuiltin,
        },
    },
    prettier,
);
