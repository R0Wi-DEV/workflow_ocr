import Vue from 'vue'

beforeEach(() => {
    global.t = jest.fn()

    // Create the element that Vue will mount to
    const element = document.createElement('div')
    element.id = 'workflow_ocr_globalsettings'
    document.body.appendChild(element)
})

// TODO :: extend this testcases by mocking Vue
test('globalSettings.js sets global t variable', async () => {
	await import('../globalSettings.js')
	expect(Vue.prototype.t).toBeDefined()
})
