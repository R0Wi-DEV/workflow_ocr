beforeEach(() => {
	global.OCA = { WorkflowEngine: { registerOperator: jest.fn() } }
	global.t = jest.fn()
})

test('main registers component at workflow engine', async () => {
	// window.OCA.WorkflowEngine.registerOperator
	await import('../main.js')
	expect(global.OCA.WorkflowEngine.registerOperator).toHaveBeenCalledTimes(1)
})
