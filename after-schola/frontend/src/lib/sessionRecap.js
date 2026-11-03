// Normalisasi data sesi dari GET /sessions (dan GET /sessions/:id) untuk rekap trainer.
// Dokumentasi & check-in trainer ada di session.trainer_attendances[]
// (field: trainer_id, status, photo_url, check_in_at, latitude, longitude, note).

import api from '@/lib/api'

// URL foto dari backend kadang relatif ('/storage/...' atau 'folder/foto.jpg'). Browser akan mencarinya
// di server frontend (Vite, :5173) padahal filenya ada di server API, jadi arahkan ke alamat API.
// Asumsi: path tanpa '/' di depan = disk publik Laravel (/storage/...).
function mediaOrigin() {
  try {
    return new URL(api.defaults?.baseURL || '/', window.location.href).origin
  } catch (e) {
    return ''
  }
}

export function resolveMediaUrl(u) {
  if (!u || /^(https?:|data:|blob:)/i.test(u)) return u
  return `${mediaOrigin()}${u.startsWith('/') ? '' : '/storage/'}${u}`
}

function hhmm(v) {
  if (!v) return ''
  const m = String(v).match(/(\d{1,2}):(\d{2})/)
  return m ? `${m[1].padStart(2, '0')}:${m[2]}` : ''
}

// Check-in milik trainer pengampu sesi (kalau tidak ada yang cocok, ambil yang pertama).
function attendanceOf(s) {
  const list = Array.isArray(s.trainer_attendances) ? s.trainer_attendances : []
  const tid = s.trainer_id ?? s.trainer?.id
  return list.find((a) => String(a.trainer_id) === String(tid)) ?? list[0] ?? null
}

export function normalizeSession(s) {
  const start = hhmm(s.start_time ?? s.time_start ?? s.time)
  const end = hhmm(s.end_time ?? s.time_end)
  const date = s.date ? String(s.date).slice(0, 10) : ''
  const att = attendanceOf(s)
  return {
    id: s.id,
    trainerId: s.trainer_id ?? s.trainer?.id ?? s.user_id ?? null,
    date,
    monthKey: date.slice(0, 7), // 'YYYY-MM'
    time: start && end ? `${start}–${end}` : start,
    school: s.school?.name || '',
    classroom: s.classroom?.name || s.classroom?.level || '',
    meetingNo: s.meeting_no ?? null,
    mode: s.mode,
    // true kalau respons ini memang membawa trainer_attendances (walau kosong)
    hasCheckinData: Array.isArray(s.trainer_attendances),
    checkin: att
      ? { status: att.status, at: att.check_in_at, note: att.note || '', latitude: att.latitude, longitude: att.longitude }
      : null,
    docs: att?.photo_url ? [resolveMediaUrl(att.photo_url)] : [],
  }
}

export function currentMonthKey() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}