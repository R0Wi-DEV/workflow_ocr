/*
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const browserslistConfig = require('@nextcloud/browserslist-config')
const { defineConfig } = require('@rspack/cli')
const {
	DefinePlugin,
	LightningCssMinimizerRspackPlugin,
	ProgressPlugin,
	SwcJsMinimizerRspackPlugin,
} = require('@rspack/core')
const NodePolyfillPlugin = require('@rspack/plugin-node-polyfill')
const browserslist = require('browserslist')
const path = require('node:path')
const { VueLoaderPlugin } = require('vue-loader')

const browsers = browserslist(browserslistConfig)
const minBrowserVersion = browsers
	.map((entry) => entry.split(' '))
	.reduce((versions, [browser, version]) => {
		const parsedVersion = parseFloat(version)
		versions[browser] = versions[browser]
			? Math.min(versions[browser], parsedVersion)
			: parsedVersion
		return versions
	}, {})
const targets = Object.entries(minBrowserVersion)
	.map(([browser, version]) => `${browser} >=${version}`)
	.join(',')

module.exports = defineConfig((env = {}) => {
	const appName = process.env.npm_package_name
	const appVersion = process.env.npm_package_version

	const mode = (env.development && 'development')
		|| (env.production && 'production')
		|| process.env.NODE_ENV
		|| 'production'
	const isDev = mode === 'development'
	process.env.NODE_ENV = mode

	return {
		target: 'web',
		mode,
		devtool: 'source-map',

		entry: {
			main: path.resolve(__dirname, 'src', 'main.js'),
			globalSettings: path.resolve(__dirname, 'src', 'globalSettings.js'),
		},

		output: {
			path: path.resolve(__dirname, 'js'),
			filename: `${appName}-[name].js?v=[contenthash]`,
			chunkFilename: `${appName}-[name].js?v=[contenthash]`,
			assetModuleFilename: '[name][ext]?v=[contenthash]',
			publicPath: 'auto',
			clean: true,
			devtoolNamespace: appName,
			devtoolModuleFilenameTemplate(info) {
				const rootDir = process.cwd()
				const rel = path.relative(rootDir, info.absoluteResourcePath)
				return `webpack:///${appName}/${rel}`
			},
		},

		optimization: {
			chunkIds: 'named',
			splitChunks: {
				automaticNameDelimiter: '-',
				cacheGroups: {
					defaultVendors: {
						reuseExistingChunk: true,
					},
				},
			},
			minimize: !isDev,
			minimizer: [
				new SwcJsMinimizerRspackPlugin({
					minimizerOptions: {
						targets,
					},
				}),
				new LightningCssMinimizerRspackPlugin({
					minimizerOptions: {
						targets,
					},
				}),
			],
		},

		module: {
			rules: [
				{
					test: /\.vue$/,
					loader: 'vue-loader',
					options: {
						experimentalInlineMatchResource: true,
					},
				},
				{
					test: /\.m?js$/,
					exclude: /node_modules/,
					loader: 'builtin:swc-loader',
					options: {
						jsc: {
							parser: {
								syntax: 'ecmascript',
							},
						},
						env: {
							targets,
						},
					},
					type: 'javascript/auto',
				},
				{
					test: /\.css$/,
					use: [
						'style-loader',
						'css-loader',
					],
				},
				{
					test: /\.s[ac]ss$/i,
					use: [
						'style-loader',
						'css-loader',
						'sass-loader',
					],
				},
				{
					test: /\.(png|jpe?g|gif|svg|webp)$/i,
					type: 'asset',
				},
				{
					test: /\.(woff2?|eot|ttf|otf)$/i,
					type: 'asset/resource',
				},
			],
		},

		plugins: [
			new ProgressPlugin(),
			new VueLoaderPlugin(),
			new NodePolyfillPlugin(),
			new DefinePlugin({
				IS_DESKTOP: false,
				__IS_DESKTOP__: false,
				__VUE_OPTIONS_API__: true,
				__VUE_PROD_DEVTOOLS__: false,
				appName: JSON.stringify(appName),
				appVersion: JSON.stringify(appVersion),
			}),
		],

		resolve: {
			extensions: ['.js', '.vue', '.json'],
			symlinks: false,
			fallback: {
				fs: false,
			},
		},

		cache: true,
	}
})
