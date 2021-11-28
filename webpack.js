const path = require('path')

// Use default workflow vue config from
// https://github.com/nextcloud/webpack-vue-config/blob/master/webpack.js
const webpackConfig = require('@nextcloud/webpack-vue-config')

// Add admin component
webpackConfig.entry.admin = path.resolve(path.join('src', 'admin.js'))

module.exports = webpackConfig