// Unduh respons blob dari API (Excel/PDF) sebagai berkas; nama diambil dari header Content-Disposition.
export function saveBlob(res, fallbackName) {
  const link = document.createElement('a')
  link.href = URL.createObjectURL(new Blob([res.data], { type: res.headers['content-type'] }))
  const m = /filename="?([^";]+)"?/.exec(res.headers['content-disposition'] || '')
  link.download = m ? m[1] : fallbackName
  document.body.appendChild(link)
  link.click()
  link.remove()
  setTimeout(() => URL.revokeObjectURL(link.href), 1000)
}
