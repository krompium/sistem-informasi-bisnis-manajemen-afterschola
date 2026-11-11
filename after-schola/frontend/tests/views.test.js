// Pemeriksaan render halaman yang bergantung pada permission dan data API: dashboard bisnis (MGT-60 sampai 63),
// raport di portal ortu (ORT-11), dan blok perpanjangan (ORT-12). API di-mock; tidak menyentuh server.
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const routes = {}
vi.mock('@/lib/api', () => ({
  default: {
    get: vi.fn(async (url, cfg) => { if (!(url in routes)) throw Object.assign(new Error('x'), { response: { status: 404 } }); return { data: typeof routes[url] === 'function' ? routes[url](cfg) : routes[url] } }),
    post: vi.fn(async () => ({ data: { data: {} } })),
  },
  STORAGE_TOKEN_KEY: 'token',
}))
import api from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import BusinessDashboardView from '@/views/BusinessDashboardView.vue'
import ReportsView from '@/views/portal/ReportsView.vue'
import ChildDetailView from '@/views/portal/ChildDetailView.vue'

async function mountView(component, path, user, routePath = '/:p(.*)*') {
  const pinia = createPinia(); setActivePinia(pinia)
  const auth = useAuthStore(); auth.user = user; auth.token = 't'
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: routePath, component }] })
  router.push(path); await router.isReady()
  const wrapper = mount(component, { global: { plugins: [pinia, router] } })
  await flushPromises()
  return wrapper
}
const mgmt = (permissions) => ({ name: 'M', roles: ['management'], permissions, branch_scope: 'all' })

beforeEach(() => {
  for (const k of Object.keys(routes)) delete routes[k]
  api.get.mockClear(); api.post.mockClear()
  globalThis.URL.createObjectURL = vi.fn(() => 'blob:x'); globalThis.URL.revokeObjectURL = vi.fn()
  vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {}) // jsdom tidak mengimplementasikan navigasi unduhan
})

describe('BusinessDashboardView', () => {
  const kpi = { period: { from: '2026-11-01', to: '2026-11-05' }, branch_id: null, branches: [{ id: 1, name: 'Cabang Bandung' }], students_active: { value: 4 }, income: { value: 2650000 },
    retention: { value: null, numerator: 0, denominator: 0 }, occupancy: { value: 18.2, numerator: 4, denominator: 22 } }
  it('menampilkan KPI, funnel, panel, dan tombol unduh', async () => {
    routes['/dashboard/kpi'] = { data: kpi }
    routes['/dashboard/funnel'] = { data: { stages: [{ key: 'calon', label: 'Calon baru', count: 13 }, { key: 'lunas', label: 'Lunas', count: 5 }] } }
    routes['/dashboard/panel'] = { data: { sessions_today: 1, students_present_today: 0, registrations_waiting: 1, payments_waiting: 1, invoices_overdue: 0 } }
    const w = await mountView(BusinessDashboardView, '/management', mgmt(['view dashboard']))
    const t = w.text()
    for (const x of ['Siswa aktif', 'Income', 'Rp', 'Retensi', 'Okupansi kelas', '18.2%', 'Operasional hari ini', 'Menunggu verifikasi', 'Funnel pendaftaran', 'Calon baru', 'Unduh Excel', 'Unduh PDF', 'Cabang Bandung']) expect(t, x).toContain(x)
    expect(t).toContain('–') // retensi null
    routes['/exports/dashboard/excel'] = () => new Blob(['x'])
    await w.findAll('button').find((b) => b.text().includes('Unduh Excel')).trigger('click'); await flushPromises()
    expect(api.get).toHaveBeenCalledWith('/exports/dashboard/excel', expect.objectContaining({ responseType: 'blob' }))
  })
  it('tanpa permission view dashboard: pesan akses, tanpa panggilan API', async () => {
    const w = await mountView(BusinessDashboardView, '/management', mgmt([]))
    expect(w.text()).toContain('belum punya akses dashboard bisnis')
    expect(api.get).not.toHaveBeenCalled()
  })
})

describe('ReportsView', () => {
  it('menampilkan raport terkirim dengan skor rubrik', async () => {
    routes['/parent/children/7/reports'] = { data: [{ id: 3, period: '2026-11', summary: 'Berkembang baik.', enrollment: { student: { name: 'Hana' }, class_group: { name: 'Coding Kids A' } }, criteria: [{ id: 1, name: 'Logika', max_score: 10, score: 9 }] }] }
    const w = await mountView(ReportsView, '/portal/anak/7/raport', { name: 'Ortu', roles: ['parent'], permissions: [] }, '/portal/anak/:id/raport')
    for (const x of ['Periode 2026-11', 'Hana', 'Logika', '9 / 10', 'Berkembang baik.', 'Unduh PDF']) expect(w.text(), x).toContain(x)
  })
  it('kosong: pesan belum ada raport', async () => {
    routes['/parent/children/7/reports'] = { data: [] }
    const w = await mountView(ReportsView, '/portal/anak/7/raport', { name: 'Ortu', roles: ['parent'], permissions: [] }, '/portal/anak/:id/raport')
    expect(w.text()).toContain('Belum ada raport')
  })
})

describe('ChildDetailView (perpanjangan)', () => {
  const child = (stage) => ({ id: 7, name: 'Hana', stage, stage_label: 'Aktif', education_level: 'sd', origin_school: 'SDN 1', branch: { name: 'Bandung' }, program: { name: 'Coding Kids', price: 500000 }, trial_starts_at: null, evaluation: null, decision: null, registration: null })
  it('anak aktif: ada link raport dan tombol perpanjangan dengan konfirmasi', async () => {
    routes['/parent/children/7'] = { data: child('aktif') }
    const w = await mountView(ChildDetailView, '/portal/anak/7', { name: 'Ortu', roles: ['parent'], permissions: [] }, '/portal/anak/:id')
    expect(w.text()).toContain('Lihat raport')
    expect(w.text()).toContain('Ajukan perpanjangan')
    expect(w.text()).not.toContain('Ya, ajukan')
    await w.findAll('button').find((b) => b.text() === 'Ajukan perpanjangan').trigger('click')
    expect(w.text()).toContain('Terbitkan invoice perpanjangan sekarang?')
    await w.findAll('button').find((b) => b.text() === 'Ya, ajukan').trigger('click'); await flushPromises()
    expect(api.post).toHaveBeenCalledWith('/parent/children/7/renewal')
  })
  it('anak belum aktif: tanpa raport dan perpanjangan', async () => {
    routes['/parent/children/7'] = { data: child('siap_daftar') }
    const w = await mountView(ChildDetailView, '/portal/anak/7', { name: 'Ortu', roles: ['parent'], permissions: [] }, '/portal/anak/:id')
    expect(w.text()).not.toContain('Lihat raport')
    expect(w.text()).not.toContain('Ajukan perpanjangan')
  })
})
