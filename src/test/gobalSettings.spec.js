import Vue from 'vue'

beforeEach(() => {
    global.t = jest.fn()
})

// TODO :: extend this testcases by mocking Vue
test('globalSettings.js sets global t variable', async () => {
    await import('../globalSettings.js')
    expect(Vue.prototype.t).toBeDefined()
})

