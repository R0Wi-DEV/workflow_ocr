/**
 * @copyright Copyright (c) 2016 Morris Jobke <hey@morrisjobke.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

(function() {

	var Component = {
		name: 'WorkflowOcr',
		render: function (createElement) {
			var self = this
			return createElement('div', {
				style: {
					width: '100%'
				},
			}, [
				createElement('a', {
					attrs: {
						href: self.link
					},
					style: {
						color: 'var(--color-text-maxcontrast)'
					}
				}, self.description)
			])
		},
		props: {
			value: ''
		},
		data: function () {
			return {
				description: t('workflow_ocr', 'Go to documentation') + '↗', 
				link: 'https://github.com/R0Wi/nextcloud_workflow_ocr/blob/master/README.md'
			}
		}
	};

	OCA.WorkflowEngine.registerOperator({
		id: 'OCA\\WorkflowOcr\\Operation',
		operation: '',
		options: Component
	});

})();
