// Kontak WhatsApp resmi AfterSchola (sama dengan tombol di landing page).
export const WHATSAPP_URL = 'https://wa.me/6282338089915'

export function whatsappLink(text) {
  return `${WHATSAPP_URL}?text=${encodeURIComponent(text)}`
}
