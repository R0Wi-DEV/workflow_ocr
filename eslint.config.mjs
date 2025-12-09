import { recommended } from '@nextcloud/eslint-config'

export default [
    ...recommended,
    {
        files: ['src/test/**', 'src/test/**/*.js', 'src/test/**/*.spec.js'],
        languageOptions: {
            globals: {
                describe: 'readonly',
                test: 'readonly',
                expect: 'readonly',
                beforeEach: 'readonly',
                afterEach: 'readonly',
                vi: 'readonly',
            },
        },
    },
    {
        name: 'workflow_ocr/ignores',
        ignores: [
            // Generated / vendor files
            'js/*',
            'vendor',
            'build',
            '*.spec.js',
        ],
    },
]
