import WorkflowOcr from './components/WorkflowOcr'
import { translate as t } from '@nextcloud/l10n'
import Vue from 'vue'

Vue.mixin({
	methods: {
		t,
	},
})

window.OCA.WorkflowEngine.registerOperator({
	id: 'OCA\\WorkflowOcr\\Operation',
	name: t('workflow_ocr'),
	description: t('workflow_ocr'),
	operation: '',
	options: WorkflowOcr,
})
