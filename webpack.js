const path = require('path')

// Use default workflow vue config from
// https://github.com/nextcloud/webpack-vue-config/blob/master/webpack.config.js
const webpackConfig = require('@nextcloud/webpack-vue-config')

// Add admin component
webpackConfig.entry.globalSettings = path.resolve(path.join('src', 'globalSettings.js'))

module.exports = webpackConfig