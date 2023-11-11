// These modules will be transformed to commonjs modules
const ignorePatterns = [
	'ansi-regex',
	'@mdi/svg',
	'bail',
	'comma-separated-tokens',
	'char-regex',
	'decode-named-character-reference',
	'devlop',
	'escape-string-regexp',
	'hast-.*',
	'is-.*',
	'mdast-.*',
	'micromark',
	'micromark-.*',
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
]

module.exports = {
	collectCoverage: true,

	collectCoverageFrom: [
		'src/**/*.{js,vue}',
		'!src/test/**',
		'!**/node_modules/**',
	],

	coverageReporters: [
		'text-summary',
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
	},

	transform: {
		'\\.js$': 'babel-jest',
		'\\.vue$': '@vue/vue2-jest',
	},
}
