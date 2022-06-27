import {} from 'regenerator-runtime/runtime'

const oc = {
	getLanguage: function() {
		return 'en-GB'
	},

	getLocale: function() {
		return 'en_GB'
	},

	isUserAdmin: function() {
		return true
	},

	Util: {
		naturalSortCompare: function(_a, _b) {
			return 0
		},
	},
}

global.OC = oc

global.TRANSLATIONS = []
global.SCOPE_VERSION = 1
