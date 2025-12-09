export function captureConsole(method = 'error') {
	const calls = []
	const original = console[method]
	const spy = (...args) => calls.push(args)
	console[method] = spy
	return {
		get calls() { return calls },
		restore() { console[method] = original }
	}
}
