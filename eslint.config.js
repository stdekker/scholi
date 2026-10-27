import js from '@eslint/js';
import globals from 'globals';

export default [
    {
        ignores: ['assets/vendor/', 'public/', 'var/', 'vendor/', 'node_modules/'],
    },
    js.configs.recommended,
    {
        files: ['assets/**/*.js'],
        languageOptions: {
            globals: globals.browser,
        },
    },
    {
        files: ['tests/js/**/*.js', '*.config.js'],
        languageOptions: {
            globals: globals.node,
        },
    },
];
