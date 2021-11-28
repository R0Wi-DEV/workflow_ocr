module.exports = {
    preset: '@vue/cli-plugin-unit-jest',
    collectCoverage: true,
    collectCoverageFrom: [
      "src/**/*.{js,vue}",
      "!**/node_modules/**"
    ],
    coverageReporters: [
      "text-summary",
      "json",
      "html"
    ],
    testMatch: [
      "**/src/test/*.spec.js"
    ],
    "transformIgnorePatterns": [
      "node_modules\/(?!(vue-material-design-icons)\/)"
    ]
  }
  