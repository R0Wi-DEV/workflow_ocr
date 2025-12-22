/*
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const { defineConfig } = require('@rsbuild/core')
const { pluginVue } = require('@rsbuild/plugin-vue')
const { pluginSass } = require('@rsbuild/plugin-sass')
const { pluginNodePolyfill } = require('@rsbuild/plugin-node-polyfill')
const path = require('node:path')

module.exports = defineConfig((context = {}) => {
	const env = context.env || {}
	const isDev =
		Boolean(env.development) ||
		(!env.production && process.env.NODE_ENV === 'development')

	if (isDev) {
		console.log('Building in development mode')
	} else {
		console.log('Building in production mode')
	}

	return {
		plugins: [
			pluginVue(),
			pluginSass(),
			pluginNodePolyfill(),
		],
		source: {
			entry: {
				main: path.resolve(__dirname, 'src', 'main.js'),
				globalSettings: path.resolve(__dirname, 'src', 'globalSettings.js'),
			},
		},
		output: {
			distPath: {
				root: path.resolve(__dirname, 'js'),
				js: '.',
			},
			filename: {
				js: `${process.env.npm_package_name}-[name].js?v=[contenthash]`,
			},
			chunkFilename: `${process.env.npm_package_name}-[name].js?v=[contenthash]`,
			assetModuleFilename: '[name][ext]',
			publicPath: 'auto',
			injectStyles: true,
			clean: true,
			legalComments: 'none',
			devtoolNamespace: process.env.npm_package_name,
			devtoolModuleFilenameTemplate(info) {
				const rootDir = process.cwd()
				const rel = path.relative(rootDir, info.absoluteResourcePath)
				return `webpack:///${process.env.npm_package_name}/${rel}`
			},
		},
		define: {
			IS_DESKTOP: false,
			__IS_DESKTOP__: false,
			__VUE_OPTIONS_API__: true,
			__VUE_PROD_DEVTOOLS__: false,
			__webpack_public_path__: JSON.stringify(process.env.WEBPACK_PUBLIC_PATH || `/apps/${process.env.npm_package_name}/js/`),
			appName: JSON.stringify(process.env.npm_package_name),
			appVersion: JSON.stringify(process.env.npm_package_version),
		},
		resolve: {
			extensions: ['.js', '.vue', '.json'],
		},
		tools: {
			rspack: {
				cache: true,
				devtool: isDev ? 'source-map' : false,
				resolve: {
					symlinks: false,
					fallback: {
						fs: false,
					},
				},
				optimization: {
					splitChunks: false,
					minimize: !isDev,
				},
			},
			htmlPlugin: false,
		},
	}
})