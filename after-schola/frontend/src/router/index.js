import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AppLayout from '@/layouts/AppLayout.vue'
import LoginView from '@/views/LoginView.vue'
import TrainerDashboardView from '@/views/TrainerDashboardView.vue'
import ManagementDashboardView from '@/views/ManagementDashboardView.vue'
import PlaceholderView from '@/views/PlaceholderView.vue'
import ManagementScheduleView from '@/views/management/ScheduleView.vue'
import ManagementTrainerAssignmentView from '@/views/management/TrainerAssignmentView.vue'
import ManagementSchoolsAdminView from '@/views/management/SchoolsAdminView.vue'
import ManagementRecapListView from '@/views/management/AttendanceRecapListView.vue'
import ManagementRecapDetailView from '@/views/management/AttendanceRecapDetailView.vue'
import TrainerScheduleView from '@/views/trainer/ScheduleView.vue'
import TrainerSchoolsView from '@/views/trainer/SchoolsView.vue'
import AttendanceSessionsView from '@/views/AttendanceSessionsView.vue'
import SessionAttendanceView from '@/views/SessionAttendanceView.vue'
import ExpoReportsView from '@/views/ExpoReportsView.vue'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { guestOnly: true },
  },
  {
    path: '/',
    component: AppLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: () => useAuthStore().homeRoute },

      // ---- Trainer ----
      { path: 'trainer', name: 'trainer.dashboard', component: TrainerDashboardView },
      { path: 'trainer/sekolah', name: 'trainer.schools', component: TrainerSchoolsView },
      { path: 'trainer/jadwal', name: 'trainer.schedule', component: TrainerScheduleView },
      { path: 'trainer/absensi', name: 'trainer.attendance', component: AttendanceSessionsView },
      {
        path: 'trainer/absensi/:id',
        name: 'trainer.attendance.session',
        component: SessionAttendanceView,
      },
      { path: 'trainer/laporan-ekspo', name: 'trainer.expo', component: ExpoReportsView },

      // ---- Management ----
      { path: 'management', name: 'management.dashboard', component: ManagementDashboardView },
      { path: 'management/sekolah-murid', name: 'management.schools', component: ManagementSchoolsAdminView },
      {
        path: 'management/penugasan-trainer',
        name: 'management.trainer-assignment',
        component: ManagementTrainerAssignmentView,
      },
      { path: 'management/absensi', name: 'management.recap', component: ManagementRecapListView },
      {
        path: 'management/absensi/sekolah/:id',
        name: 'management.recap.school',
        component: ManagementRecapDetailView,
      },
      {
        path: 'management/absensi/:id',
        name: 'management.attendance.session',
        component: SessionAttendanceView,
      },
      { path: 'management/jadwal', name: 'management.schedule', component: ManagementScheduleView },
      { path: 'management/laporan-ekspo', name: 'management.expo', component: ExpoReportsView },
      {
        path: 'management/manajemen-user',
        component: PlaceholderView,
        meta: { title: 'Manajemen User', icon: 'manage_accounts' },
      },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// Guard: lindungi halaman ber-auth, muat profil, arahkan sesuai peran.
router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (auth.isAuthenticated && !auth.user) {
    try {
      await auth.fetchMe()
    } catch (e) {
      auth.setToken(null)
      if (to.meta.requiresAuth) {
        return { name: 'login' }
      }
    }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return auth.homeRoute
  }

  // Isolasi antar-peran: trainer tak boleh masuk /management dan sebaliknya.
  if (auth.isAuthenticated && auth.user) {
    if (to.path.startsWith('/management') && !auth.isManagement) return auth.homeRoute
    if (to.path.startsWith('/trainer') && !auth.isTrainer) return auth.homeRoute
  }

  return true
})

export default router
