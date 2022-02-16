import Vue from 'vue'
import GlobalSettings from './components/GlobalSettings'

Vue.prototype.t = t
const App = Vue.extend(GlobalSettings)
const appInstance = new App()
appInstance.$mount('#workflow_ocr_globalsettings')
