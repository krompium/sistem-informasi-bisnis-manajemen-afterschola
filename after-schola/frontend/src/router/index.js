import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AppLayout from '@/layouts/AppLayout.vue'
import LandingPage from '@/views/public/LandingPage.vue'
import LoginView from '@/views/LoginView.vue'
import TrainerDashboardView from '@/views/TrainerDashboardView.vue'
import ManagementDashboardView from '@/views/ManagementDashboardView.vue'
import PlaceholderView from '@/views/PlaceholderView.vue'
import ManagementScheduleView from '@/views/management/ScheduleView.vue'
import ManagementTrainerAssignmentView from '@/views/management/TrainerAssignmentView.vue'
import ManagementSchoolsAdminView from '@/views/management/SchoolsAdminView.vue'
import ManagementRecapListView from '@/views/management/AttendanceRecapListView.vue'
import ManagementRecapDetailView from '@/views/management/AttendanceRecapDetailView.vue'
import ManagementUserManagementView from '@/views/management/UserManagementView.vue'
import ManagementHotIssuesView from '@/views/management/HotIssuesView.vue'
import ManagementEnrollmentView from '@/views/management/EnrollmentView.vue'
import ManagementVerificationView from '@/views/management/VerificationView.vue'
import ManagementInvoicesView from '@/views/management/InvoicesView.vue'
import ManagementAgingReportView from '@/views/management/AgingReportView.vue'
import TrainerScheduleView from '@/views/trainer/ScheduleView.vue'
import TrainerSchoolsView from '@/views/trainer/SchoolsView.vue'
import AttendanceSessionsView from '@/views/AttendanceSessionsView.vue'
import SessionAttendanceView from '@/views/SessionAttendanceView.vue'
import ExpoReportsView from '@/views/ExpoReportsView.vue'
import TrialEvaluationsView from '@/views/TrialEvaluationsView.vue'
import TrialRegistrationView from '@/views/public/TrialRegistrationView.vue'
import ManagementProgramView from '@/views/management/ProgramManagementView.vue'
import PublicProgramsView from '@/views/public/PublicProgramsView.vue'
import PublicProgramDetailView from '@/views/public/PublicProgramDetailView.vue'
import ManagementPaymentSettingsView from '@/views/management/PaymentSettingsView.vue'
import ManagementProgramDetailView from '@/views/management/ProgramDetailView.vue'
import DeveloperDashboardView from '@/views/developer/DeveloperDashboardView.vue'
import ManagementTeamsView from '@/views/management/TeamsView.vue'
import ProjectsView from '@/views/projects/ProjectsView.vue'
import ProjectBoardView from '@/views/projects/ProjectBoardView.vue'
import ProjectCalendarView from '@/views/projects/ProjectCalendarView.vue'
import ProjectGanttView from '@/views/projects/ProjectGanttView.vue'
import ManagementBranchesView from '@/views/management/BranchesView.vue'

// 1) Import, di dekat import view publik lain

const routes = [
  {
    // Gerbang publik. User yang sudah login langsung diarahkan ke dashboard-nya (guard guestOnly).
    path: '/',
    name: 'landing',
    component: LandingPage,
    meta: { guestOnly: true },
  },
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { guestOnly: true },
  },
  {
    path: '/daftar-trial',
    name: 'public.trial-registration',
    component: TrialRegistrationView,
  },
  { path: '/program', name: 'public.programs', component: PublicProgramsView },
  { path: '/program/:id', name: 'public.program-detail', component: PublicProgramDetailView },
  {
    path: '/',
    component: AppLayout,
    meta: { requiresAuth: true },
    children: [
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
      { path: 'trainer/evaluasi-trial', name: 'trainer.trial', component: TrialEvaluationsView },

            // ---- Developer (Modul 12: Manajemen Proyek) ----
      
      { path: 'developer', name: 'developer.dashboard', component: DeveloperDashboardView },
      { path: 'developer/proyek', name: 'developer.projects', component: ProjectsView },
      { path: 'developer/proyek/:id', name: 'developer.project.board', component: ProjectBoardView },
	  { path: 'developer/kalender', name: 'developer.calendar', component: ProjectCalendarView },
	  { path: 'developer/proyek/:id/gantt', name: 'developer.project.gantt', component: ProjectGanttView },
      
      
	  

      // ---- Orang Tua (portal belum dibangun: Fase 2) ----
      {
        path: 'portal',
        name: 'portal.dashboard',
        component: PlaceholderView,
        meta: { title: 'Portal Orang Tua', icon: 'family_restroom' },
      },

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
      { path: 'management/evaluasi-trial', name: 'management.trial', component: TrialEvaluationsView },
      { path: 'management/keputusan-pendaftaran', name: 'management.enrollment', component: ManagementEnrollmentView },
      { path: 'management/verifikasi-pendaftaran', name: 'management.verification', component: ManagementVerificationView },
      { path: 'management/invoice', name: 'management.invoices', component: ManagementInvoicesView },
      { path: 'management/umur-piutang', name: 'management.aging', component: ManagementAgingReportView },
      { path: 'management/pengaturan-pembayaran', name: 'management.payment-settings', component: ManagementPaymentSettingsView },
      { path: 'management/hot-issue', name: 'management.hot-issues', component: ManagementHotIssuesView },
      {
        path: 'management/manajemen-user',
        name: 'management.users',
        component: ManagementUserManagementView,
        meta: { title: 'Manajemen User', icon: 'manage_accounts' },
      },
      {
        path: 'management/cabang',
        name: 'management.branches',
        component: ManagementBranchesView,
        meta: { title: 'Cabang', icon: 'store' },
      },
      {
        path: 'management/program',
        name: 'management.programs',
        component: ManagementProgramView,
        meta: { title: 'Program & Promosi', icon: 'inventory_2' },
      },
      {
        path: 'management/program/:id',
        name: 'management.programs.detail',
        component: ManagementProgramDetailView,
        meta: { title: 'Detail Program', icon: 'inventory_2' },
      },
	  { path: 'management/team', name: 'management.teams', component: ManagementTeamsView },
      { path: 'management/proyek', name: 'management.projects', component: ProjectsView },
      { path: 'management/proyek/:id', name: 'management.project.board', component: ProjectBoardView },
	  { path: 'management/kalender', name: 'management.calendar', component: ProjectCalendarView },
	  { path: 'management/proyek/:id/gantt', name: 'management.project.gantt', component: ProjectGanttView },
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
	if (to.path.startsWith('/developer') && !auth.isDeveloper) return auth.homeRoute
    if (to.path.startsWith('/portal') && !auth.isParent) return auth.homeRoute
  }

  return true
})

export default router