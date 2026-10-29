<script setup>
// Bagian "akses" pada form user: scope cabang, pilihan cabang, dan permission fungsi (MGT-02).
// Dipakai form tambah dan form ubah di UserManagementView.
defineProps({
  branches: { type: Array, default: () => [] }, // cabang yang boleh dipilih pelaku
  canSetAll: { type: Boolean, default: false }, // hanya pusat (scope 'all') yang boleh memberi scope 'all'
  showPermissions: { type: Boolean, default: false }, // permission fungsi hanya untuk role Management
})

const scope = defineModel('scope', { default: 'assigned' })
const branchIds = defineModel('branchIds', { default: () => [] })
const permissions = defineModel('permissions', { default: () => [] })

// Pengelompokan mengikuti PRD 3.2. Nama permission harus sama dengan FunctionPermissions::ALL di backend;
// backend menolak (422) nama yang tidak dikenal.
const FUNCTIONS = [
  { label: 'Marketing', perms: ['manage programs', 'manage trial slots', 'manage leads'] },
  { label: 'Admin pendaftaran', perms: ['verify registrations'] },
  { label: 'Koordinator program', perms: ['manage classes', 'manage schedule', 'assign trainers', 'review reports'] },
  { label: 'Pimpinan', perms: ['view dashboard', 'view all attendance', 'view finance reports'] },
  { label: 'Admin sistem', perms: ['manage users', 'manage branches', 'manage integrations'] },
  { label: 'Keuangan', perms: ['confirm payments', 'manage invoices', 'view finance reports', 'export trainer attendance'] },
]

const segment = (active) =>
  active ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant'
</script>

<template>
  <div class="flex flex-col gap-space-md">
    <div class="flex flex-col gap-1">
      <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Cakupan Cabang</span>
      <div class="inline-flex rounded-xl bg-surface-container-low p-1">
        <button
          type="button"
          class="flex flex-1 items-center justify-center rounded-lg px-space-md py-2 font-label-md text-label-md font-semibold transition-all"
          :class="segment(scope === 'assigned')"
          @click="scope = 'assigned'"
        >
          Cabang tertentu
        </button>
        <button
          v-if="canSetAll"
          type="button"
          class="flex flex-1 items-center justify-center rounded-lg px-space-md py-2 font-label-md text-label-md font-semibold transition-all"
          :class="segment(scope === 'all')"
          @click="scope = 'all'"
        >
          Semua cabang (pusat)
        </button>
      </div>
    </div>

    <div v-if="scope === 'assigned'" class="flex flex-col gap-1">
      <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Cabang <span class="font-normal text-outline">(minimal satu)</span></span>
      <div class="flex flex-col gap-1 rounded-lg border border-outline-variant bg-white p-space-sm">
        <label v-for="b in branches" :key="b.id" class="flex items-center gap-space-sm py-1">
          <input v-model="branchIds" type="checkbox" :value="b.id" class="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary/30" />
          <span class="font-body-md text-body-md text-on-surface">{{ b.name }} <span class="text-outline">({{ b.code }})</span></span>
        </label>
        <p v-if="!branches.length" class="font-body-sm text-body-sm text-outline">Belum ada cabang yang bisa dipilih.</p>
      </div>
    </div>

    <div v-if="showPermissions" class="flex flex-col gap-1">
      <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Fungsi Kerja <span class="font-normal text-outline">(permission per user)</span></span>
      <div class="flex flex-col gap-space-sm rounded-lg border border-outline-variant bg-white p-space-sm">
        <div v-for="f in FUNCTIONS" :key="f.label" class="flex flex-col gap-1">
          <span class="font-label-sm text-label-sm font-semibold uppercase tracking-wider text-secondary">{{ f.label }}</span>
          <label v-for="p in f.perms" :key="p" class="flex items-center gap-space-sm py-0.5">
            <input v-model="permissions" type="checkbox" :value="p" class="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary/30" />
            <span class="font-body-md text-body-md text-on-surface">{{ p }}</span>
          </label>
        </div>
      </div>
    </div>
  </div>
</template>
