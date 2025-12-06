import WorkflowOcr from './components/WorkflowOcr.vue'
import { translate as t } from '@nextcloud/l10n'

window.OCA.WorkflowEngine.registerOperator({
	id: 'OCA\\WorkflowOcr\\Operation',
	name: t('workflow_ocr'),
	description: t('workflow_ocr'),
	operation: '',
	options: WorkflowOcr,
})
