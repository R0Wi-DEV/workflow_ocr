const path = require('path')

// Use default workflow vue config from
// https://github.com/nextcloud/webpack-vue-config/blob/master/webpack.config.js
const webpackConfig = require('@nextcloud/webpack-vue-config')

// Add admin component
webpackConfig.entry.globalSettings = path.resolve(path.join('src', 'globalSettings.js'))

// Makes debugging VUE components possible, see
// https://v2.vuejs.org/v2/cookbook/debugging-in-vscode.html#Displaying-Source-Code-in-the-Browser
webpackConfig.devtool = 'source-map'

module.exports = webpackConfig
