import { vi } from 'vitest'

beforeEach(() => {
	global.OCA = { WorkflowEngine: { registerOperator: vi.fn() } }
	global.t = vi.fn()
})

test('main registers component at workflow engine', async () => {
	// window.OCA.WorkflowEngine.registerOperator
	await import('../main.js')
	expect(global.OCA.WorkflowEngine.registerOperator).toHaveBeenCalledTimes(1)
})
