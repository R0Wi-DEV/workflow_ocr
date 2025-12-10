import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = fileURLToPath(new URL('.', import.meta.url))

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src'),
      // map nextcloud packages to test mocks so component tests keep using the light mocks
      '@nextcloud/axios': path.resolve(__dirname, 'src/test/__mocks__/@nextcloud/axios.js'),
      '@nextcloud/router': path.resolve(__dirname, 'src/test/__mocks__/@nextcloud/router.js'),
      '@nextcloud/l10n': path.resolve(__dirname, 'src/test/__mocks__/@nextcloud/l10n.js'),
      '@nextcloud/dialogs': path.resolve(__dirname, 'src/test/__mocks__/@nextcloud/dialogs.js'),
      '@nextcloud/vue': path.resolve(__dirname, 'src/test/__mocks__/@nextcloud/vue.js'),
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup-vitest.js'],
    include: ['src/test/**/*.spec.js', 'src/integration-test/**/*.spec.js'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'json', 'lcov', 'html'],
      include: ['src/**/*.{js,vue}'],
      exclude: ['src/test/**', 'src/integration-test/**', 'node_modules/**'],
    },
  },
})
