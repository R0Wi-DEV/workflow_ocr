module.exports = {
    preset: '@vue/cli-plugin-unit-jest',
    collectCoverage: true,
    collectCoverageFrom: [
      "src/**/*.{js,vue}",
      "!src/test/**",
      "!**/node_modules/**"
    ],
    coverageReporters: [
      "text-summary",
      "json",
      "lcov",
      "html"
    ],
    testMatch: [
      "**/src/test/*.spec.js",
      "**/src/test/**/*.spec.js"
    ],
    transformIgnorePatterns: [
      "node_modules\/(?!(vue-material-design-icons)\/)",
      //"node_modules/(?!@babel)"
    ],
    setupFilesAfterEnv: ['<rootDir>/src/test/setup-jest.js']
  }
  