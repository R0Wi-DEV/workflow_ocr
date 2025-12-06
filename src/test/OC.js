export default {

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
		naturalSortCompare: function(a, b) {
			return 0
		},
	},

	coreApps: [
		'',
		'admin',
		'log',
		'core/search',
		'core',
		'3rdparty',
	],

	appswebroots: {
		calendar: '/apps/calendar',
		deck: '/apps/deck',
		files: '/apps/files',
		spreed: '/apps/spreed',
	},

	config: {

	},
}
