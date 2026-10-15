/**
 * @type {import('prettier').Config &
 *   import('prettier-plugin-tailwindcss').PluginOptions}
 */
export default {
    printWidth: 80,
    tabWidth: 4,
    useTabs: false,
    singleQuote: true,
    semi: true,
    singleAttributePerLine: false,
    htmlWhitespaceSensitivity: 'css',
    endOfLine: 'lf',
    plugins: ['prettier-plugin-tailwindcss'],
    tailwindStylesheet: './resources/css/app.css',
    tailwindFunctions: ['clsx', 'cn', 'cva'],
};
