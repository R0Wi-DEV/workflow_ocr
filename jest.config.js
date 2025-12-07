// These modules will be transformed to commonjs modules
const ignorePatterns = [
	'@nextcloud/.*',
	'@vueuse/.*',
	'floating-vue',
	'ansi-regex',
	'@mdi/svg',
	'bail',
	'comma-separated-tokens',
	'ccount',
	'char-regex',
	'decode-named-character-reference',
	'devlop',
	'escape-string-regexp',
	'hast-.*',
	'is-.*',
	'longest-streak',
	'mdast-.*',
	'micromark',
	'micromark-.*',
	'markdown-table',
	'property-information',
	'rehype-.*',
	'remark-.*',
	'space-separated-tokens',
	'string-length',
	'strip-ansi',
	'trim-lines',
	'trough',
	'tributejs',
	'unified',
	'unist-.*',
	'vfile',
	'vfile-.*',
	'vue-material-design-icons',
	'web-namespaces',
	'zwitch',
]

module.exports = {
	collectCoverage: true,

	coverageProvider: 'v8',

	collectCoverageFrom: [
		'src/**/*.{js,vue}',
		'!src/test/**',
		'!**/node_modules/**',
	],

	coverageReporters: [
		'text',
		'json',
		'lcov',
		'html',
	],

	testMatch: [
		'**/src/test/*.spec.js',
		'**/src/test/**/*.spec.js'
	],

	transformIgnorePatterns: [
		'node_modules/(?!(' + ignorePatterns.join('|') + ')/)',
	],

	setupFilesAfterEnv: ['<rootDir>/src/test/setup-jest.js'],

	testEnvironment: 'jest-environment-jsdom',

	moduleFileExtensions: [
		'js',
		'vue',
	],

	moduleNameMapper: {
		'\\.(css|scss)$': 'jest-transform-stub',
		'^@nextcloud/axios$': '<rootDir>/src/test/__mocks__/@nextcloud/axios.js',
		'^@nextcloud/router$': '<rootDir>/src/test/__mocks__/@nextcloud/router.js',
		'^@nextcloud/l10n$': '<rootDir>/src/test/__mocks__/@nextcloud/l10n.js',
		'^@vue/test-utils$': '<rootDir>/node_modules/@vue/test-utils/dist/vue-test-utils.cjs.js',
	},

	transform: {
		'\\.js$': 'babel-jest',
		'\\.vue$': '@vue/vue3-jest',
	},
}
