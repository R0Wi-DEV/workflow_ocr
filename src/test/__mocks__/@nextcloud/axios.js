// Mock for @nextcloud/axios
const mockAxios = {
	get: jest.fn(),
	post: jest.fn(),
	put: jest.fn(),
	delete: jest.fn(),
	patch: jest.fn(),
}

export default mockAxios