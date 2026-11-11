// Menu AppLayout per role. Latar belakang: array menu pernah terhapus tanpa terdeteksi karena `npm run build`
// tidak memeriksa referensi variabel yang hilang. Test ini merender komponen untuk tiap role.
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@/lib/api', () => ({ default: { get: vi.fn(async () => ({ data: { data: [] } })), post: vi.fn(async () => ({ data: {} })) }, STORAGE_TOKEN_KEY: 'token' }))
vi.mock('@/lib/usePolling', () => ({ usePolling: () => {} }))
vi.mock('@/components/HotIssueModal.vue', () => ({ default: { render: () => null } }))
vi.mock('@/components/NotificationBell.vue', () => ({ default: { render: () => null } }))

import AppLayout from '@/layouts/AppLayout.vue'
import { useAuthStore } from '@/stores/auth'

const ALL = ['manage trial slots', 'manage leads', 'verify registrations', 'manage classes', 'assign trainers', 'manage schedule', 'review reports', 'view all attendance', 'export trainer attendance', 'manage invoices', 'view finance reports', 'manage users', 'manage branches', 'manage integrations', 'manage hot issues', 'view dashboard']

async function render(user, path) {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.user = user
  auth.token = 't'
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:p(.*)*', component: { render: () => null } }] })
  router.push(path)
  await router.isReady()
  const wrapper = mount(AppLayout, { global: { plugins: [pinia, router], stubs: { RouterView: true } } })
  await flushPromises()
  return wrapper
}
const person = (roles, permissions = []) => ({ id: 1, name: 'Uji Coba', email: 'u@x.id', roles, permissions, branch_scope: 'all', branches: [] })

describe('AppLayout menu per role', () => {
  it('management dengan semua permission: grup tampil, tertutup di dashboard, terbuka saat diklik', async () => {
    const w = await render(person(['management'], ALL), '/management')
    for (const label of ['Dashboard', 'Pendaftaran', 'Kelas & Jadwal', 'Keuangan', 'Manajemen Ekstrakurikuler', 'Proyek', 'Sistem']) expect(w.text()).toContain(label)
    const child = () => w.findAll('a').find((a) => a.text().includes('Slot Trial'))
    expect(child(), 'anak grup ada di DOM').toBeTruthy()
    const hidden = () => (child().element.parentElement.getAttribute('style') || '').includes('display: none')
    expect(hidden(), 'tertutup di awal').toBe(true)
    const group = w.findAll('button').find((b) => b.text().includes('Pendaftaran'))
    expect(group, 'tombol grup Pendaftaran').toBeTruthy()
    await group.trigger('click')
    expect(hidden(), 'terbuka setelah klik').toBe(false)
    await group.trigger('click')
    expect(hidden(), 'tertutup lagi').toBe(true)
  })

  it('management membuka grup yang berisi halaman aktif', async () => {
    const w = await render(person(['management'], ALL), '/management/kelas')
    expect(w.text()).toContain('Penempatan Siswa')
    expect(w.text()).toContain('Tinjau Raport')
  })

  it('management tanpa permission fungsi: grup tanpa anak yang boleh dilihat disembunyikan', async () => {
    const w = await render(person(['management']), '/management')
    expect(w.text()).not.toContain('Keuangan')
    expect(w.text()).not.toContain('Sistem')
    expect(w.text()).toContain('Pendaftaran') // Evaluasi Trial tanpa permission
    expect(w.text()).toContain('Manajemen Ekstrakurikuler')
  })

  it('trainer: menu trainer', async () => {
    const w = await render(person(['trainer']), '/trainer')
    for (const label of ['Dashboard', 'Jadwal Saya', 'Sesi Kelas', 'Rekap Saya', 'Evaluasi Trial', 'Catatan Progres', 'Evaluasi Siswa']) expect(w.text()).toContain(label)
    expect(w.text()).not.toContain('Pendaftaran')
  })

  it('ortu: menu portal', async () => {
    const w = await render(person(['parent']), '/portal')
    for (const label of ['Beranda', 'Jadwal', 'Tagihan', 'Notifikasi']) expect(w.text()).toContain(label)
  })

  it('developer: menu developer', async () => {
    const w = await render(person(['developer']), '/developer')
    for (const label of ['Dashboard', 'Proyek', 'Kalender']) expect(w.text()).toContain(label)
  })
})
