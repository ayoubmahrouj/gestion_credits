import api from './api'

export type ExportScope = 'all' | 'clients' | 'products' | 'sales' | 'payments' | 'credits'

export interface ExportFilters {
  from?: string
  to?: string
  client_id?: number | ''
  brand_id?: number | ''
  category_id?: number | ''
  type?: string
  status?: string
  method?: string
  scope?: ExportScope
}

/**
 * Télécharge le classeur Excel généré par le backend.
 */
export async function downloadExcel(filters: Record<string, string | number | undefined | null> = {}): Promise<void> {
  const params = Object.fromEntries(
    Object.entries(filters).filter(([, value]) => value !== '' && value !== null && value !== undefined),
  )

  const response = await api.get('/exports/excel', {
    params,
    responseType: 'blob',
  })

  const disposition = String(response.headers?.['content-disposition'] ?? '')
  const match = disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i)
  const fallback = `gestion-credits-${new Date().toISOString().slice(0, 10)}.xlsx`
  const filename = decodeURIComponent(match?.[1] ?? fallback)

  const blob = new Blob([response.data], {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()

  window.setTimeout(() => URL.revokeObjectURL(url), 2000)
}
