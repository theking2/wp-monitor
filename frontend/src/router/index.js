import { createRouter, createWebHashHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import LoginView from '../views/LoginView.vue'
import SiteListView from '../views/SiteListView.vue'
import SiteDetailView from '../views/SiteDetailView.vue'

const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: '/login', name: 'login', component: LoginView },
    { path: '/', name: 'sites', component: SiteListView, meta: { requiresAuth: true } },
    {
      path: '/sites/:id',
      name: 'site-detail',
      component: SiteDetailView,
      meta: { requiresAuth: true },
      props: true,
    },
  ],
})

router.beforeEach((to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.name === 'login' && auth.isAuthenticated) {
    return { name: 'sites' }
  }

  return true
})

export default router
