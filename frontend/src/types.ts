export interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
  }
  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
}

export interface User {
  id: number
  name: string
  username?: string | null
  email: string | null
  phone: string | null
  role: 'admin' | 'manager' | 'cashier' | 'accountant'
  locale: 'fr' | 'ar'
  is_active?: boolean
  is_self?: boolean
}

export interface Client {
  id: number
  code: string
  name: string
  type: 'individual' | 'company'
  ice: string | null
  phone: string | null
  whatsapp: string | null
  email: string | null
  address: string | null
  city: string | null
  credit_limit: number
  balance: number
  available_credit: number
  score: number
  status: 'active' | 'blocked' | 'archived'
  is_active: boolean
  notes: string | null
  credits_count?: number
  sales_count?: number
  created_at: string
}

export interface Product {
  id: number
  sku: string
  barcode: string | null
  name_fr: string
  name_ar: string | null
  description: string | null
  brand: { id: number; name: string } | null
  category: { id: number; name: string } | null
  brand_id: number | null
  category_id: number | null
  unit: string
  purchase_price: number
  sale_price: number
  tva: number
  stock_qty: number
  stock_min: number
  is_low_stock: boolean
  is_active: boolean
}

export interface Installment {
  id: number
  number: number
  due_date: string
  amount: number
  paid_amount: number
  remaining: number
  status: 'pending' | 'partial' | 'paid' | 'overdue'
  is_overdue: boolean
  paid_at: string | null
}

export interface Credit {
  id: number
  sale_id: number
  client_id: number
  client?: { id: number; code: string; name: string; phone: string | null; score: number }
  principal: number
  total_due: number
  amount_paid: number
  balance: number
  start_date: string
  due_date: string | null
  status: 'active' | 'settled' | 'overdue' | 'cancelled'
  days_overdue?: number
  installments?: Installment[]
  sale?: { id: number; number: string; date: string; total: number }
}

export interface Payment {
  id: number
  client_id: number | null
  client?: { id: number; code: string; name: string; phone?: string | null; balance?: number } | null
  credit_id: number | null
  credit?: {
    id: number
    sale_id: number
    sale_number: string | null
    balance: number
    status: string
  } | null
  amount: number
  method: 'cash' | 'bank' | 'cheque' | 'card' | 'other'
  reference: string | null
  paid_at: string
  received_by?: { id: number; name: string } | null
  notes: string | null
  allocations?: { installment_id: number; amount: number }[]
}

export interface Sale {
  id: number
  number: string
  client_id: number | null
  client?: { id: number; code: string; name: string; phone: string | null; balance?: number } | null
  user?: { id: number; name: string } | null
  date: string
  type: 'cash' | 'credit'
  subtotal: number
  discount: number
  total: number
  paid_amount: number
  status: 'paid' | 'partial' | 'unpaid' | 'cancelled'
  due_date: string | null
  notes?: string | null
  created_at?: string
  items?: {
    id: number
    product_id: number
    product: { id: number; sku: string; name_fr: string; name_ar: string | null } | null
    quantity: number
    unit_price: number
    discount: number
    total: number
  }[]
  credit?: Credit | null
}

export interface Kpis {
  sales_today: number
  sales_month: number
  receivables: number
  overdue_amount: number
  payments_today: number
  dso: number
  clients_total: number
  clients_overdue: number
  low_stock: number
  avg_score: number
}

export interface Aging {
  current: number
  '1_30': number
  '31_60': number
  '61_90': number
  over_90: number
}

export interface DashboardData {
  kpis: Kpis
  aging: Aging
  top_debtors: Pick<Client, 'id' | 'code' | 'name' | 'phone' | 'balance' | 'score' | 'status'>[]
  monthly_series: { month: string; label: string; total: number }[]
  recent_activity: {
    id: number
    amount: number
    method: string
    paid_at: string
    client: string | null
  }[]
}

export interface ReportSummary {
  period: { from: string; to: string; days: number }
  sales: {
    count: number
    subtotal: number
    discount: number
    total: number
    cash: number
    credit: number
    avg_basket: number
    paid_at_sale: number
    remaining: number
    daily_average: number
    by_status: Record<'paid' | 'partial' | 'unpaid' | 'cancelled', { count: number; total: number }>
    best_day: { label: string; total: number }
  }
  payments: {
    count: number
    total: number
    avg: number
    by_method: Record<string, number>
  }
  margin: { revenue: number; cost: number; margin: number; rate: number }
  collection_rate: number
  receivables: { balance: number; overdue: number; clients: number; aging: Aging }
  clients: { active: number; new: number; with_sales: number }
  stock: {
    products: number
    value_purchase: number
    value_sale: number
    low: number
    out: number
    low_list: { id: number; sku: string; name: string; stock_qty: number; stock_min: number }[]
  }
  series: { label: string; sales: number; payments: number }[]
  series_mode: 'day' | 'month'
  top_products: { id: number; sku: string; name: string; qty: number; total: number; margin: number }[]
  top_clients: { id: number; code: string | null; name: string; count: number; total: number; balance: number }[]
  by_brand: { name: string; qty: number; total: number }[]
  by_category: { name: string; qty: number; total: number }[]
  by_user: { name: string; count: number; total: number }[]
}
