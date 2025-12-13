import { vi } from 'vitest'

// Mock for @nextcloud/axios
const mockAxios = {
	get: vi.fn(),
	post: vi.fn(),
	put: vi.fn(),
	delete: vi.fn(),
	patch: vi.fn(),
}

export default mockAxios
