// Helper format tampilan bersama (tanggal, mode sesi, status pertemuan, inisial).

export function isOnsite(mode) {
  return String(mode || '').toLowerCase() !== 'online'
}

export function modeLabel(mode) {
  return isOnsite(mode) ? 'Onsite' : 'Online'
}

export function initials(name) {
  if (!name) return 'AS'
  return name
    .split(' ')
    .slice(0, 2)
    .map((w) => w.charAt(0).toUpperCase())
    .join('')
}

export function formatDate(dateStr, opts) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  if (Number.isNaN(d.getTime())) return dateStr
  return d.toLocaleDateString(
    'id-ID',
    opts || { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' },
  )
}

export function formatDateShort(dateStr) {
  return formatDate(dateStr, { day: 'numeric', month: 'short', year: 'numeric' })
}

// Timestamp lengkap: "4 Sep 2026, 09.05" (tanggal + jam).
export function formatStamp(value) {
  if (!value) return '-'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return String(value)
  return d.toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// "09:00 – 10:30" / "09:00" / '' — dari start_time & end_time (format HH:MM).
export function sessionTime(session) {
  if (!session) return ''
  const s = session.start_time
  const e = session.end_time
  if (s && e) return `${s} – ${e}`
  if (s) return s
  return ''
}

// "Kamis, 4 Sep 2026 • 09:00 – 10:30" — tanggal + jam bila ada.
export function formatDateTime(session) {
  const d = formatDate(session?.date)
  const t = sessionTime(session)
  return t ? `${d} • ${t}` : d
}

function toDay(dateStr) {
  const d = new Date(dateStr)
  d.setHours(0, 0, 0, 0)
  return d
}

/**
 * Status pertemuan untuk badge:
 * terkunci → selesai; hari ini; akan datang; atau lewat & belum diabsen.
 */
export function sessionStatus(session) {
  if (!session) return { key: 'unknown', label: '—', cls: 'bg-surface-container text-on-surface-variant', dot: 'bg-outline' }

  if (session.is_locked) {
    return {
      key: 'locked',
      label: 'Selesai & Terkunci',
      cls: 'bg-[#D1FAE5] text-[#065F46]',
      dot: 'bg-[#10B981]',
    }
  }

  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const day = toDay(session.date)
  const diff = (day - today) / 86400000

  if (diff === 0) {
    return {
      key: 'today',
      label: 'Hari Ini',
      cls: 'bg-secondary-container text-on-secondary-container',
      dot: 'bg-primary-container',
    }
  }
  if (diff > 0) {
    return {
      key: 'upcoming',
      label: 'Akan Datang',
      cls: 'bg-surface-container-high text-on-surface-variant',
      dot: 'bg-outline',
    }
  }
  return {
    key: 'pending',
    label: 'Perlu Diabsen',
    cls: 'bg-[#FEF3C7] text-[#92400E]',
    dot: 'bg-[#F59E0B]',
  }
}

// Ambil array data dari respons Resource (terbungkus { data: [...] }) atau array polos.
export function unwrap(payload) {
  if (Array.isArray(payload)) return payload
  if (payload && Array.isArray(payload.data)) return payload.data
  return []
}
