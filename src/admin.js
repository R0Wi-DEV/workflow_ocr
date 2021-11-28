import Vue from 'vue'
import Admin from './components/Admin'

Vue.prototype.t = t
const App = Vue.extend(Admin)
const appInstance = new App()
appInstance.$mount('#workflow_ocr_admin')
