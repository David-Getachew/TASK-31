// Domain DTOs for Prompt 4 API endpoints

export type GradeItemState = 'draft' | 'published'
export type OrderStatus = 'pending_payment' | 'paid' | 'canceled' | 'refunded' | 'redeemed'
export type PaymentMethod = 'cash' | 'check' | 'local_terminal' | 'waiver'
export type PaymentStatus = 'pending' | 'succeeded' | 'failed'
export type BillStatus = 'open' | 'partial' | 'paid' | 'void' | 'past_due'
export type BillType = 'initial' | 'recurring' | 'supplemental' | 'penalty'
export type RefundStatus = 'pending' | 'approved' | 'rejected' | 'completed'
export type EnrollmentStatus = 'enrolled' | 'withdrawn' | 'completed'
export type ContentState = 'visible' | 'hidden' | 'locked'
export type PostState = 'visible' | 'hidden'
export type AppointmentStatus = 'scheduled' | 'rescheduled' | 'canceled' | 'completed'
export type NotificationCategory = 'announcements' | 'mentions' | 'billing' | 'system'
export type LedgerEntryType = 'charge' | 'payment' | 'refund' | 'reversal' | 'penalty' | 'tax_adjustment'
export type ReconciliationStatus = 'open' | 'resolved'
export type RosterImportStatus = 'running' | 'completed' | 'failed'

export interface Term {
  id: number
  name: string
  start_date: string
  end_date: string
  status: string
}

export interface Course {
  id: number
  term_id: number
  code: string
  title: string
  status: string
  term?: Term
}

export interface Section {
  id: number
  course_id: number
  term_id: number
  section_code: string
  capacity: number
  status: string
  course?: Course
}

export interface Enrollment {
  id: number
  user_id: number
  section_id: number
  status: EnrollmentStatus
  enrolled_at: string | null
  withdrawn_at: string | null
}

export interface GradeItem {
  id: number
  section_id: number
  title: string
  max_score: number
  weight_bps: number
  state: GradeItemState
  published_at: string | null
}

export interface Thread {
  id: number
  section_id: number
  course_id: number
  author_id: number
  thread_type: 'discussion' | 'announcement' | 'qa'
  title: string
  body: string
  state: ContentState
  created_at: string
  updated_at: string
}

export interface Post {
  id: number
  thread_id: number
  author_id: number
  parent_post_id: number | null
  body: string
  state: PostState
  created_at: string
  edited_at: string | null
}

export interface Comment {
  id: number
  post_id: number
  author_id: number
  body: string
  state: PostState
  created_at: string
}

export interface Notification {
  id: number
  user_id: number
  category: NotificationCategory
  type: string
  title: string
  body: string
  payload: Record<string, unknown>
  read_at: string | null
  created_at: string
}

export interface OrderLine {
  id: number
  order_id: number
  catalog_item_id: number
  quantity: number
  unit_price_cents: number
  tax_rule_snapshot: Record<string, unknown> | null
  line_total_cents: number
}

export interface Order {
  id: number
  user_id: number
  status: OrderStatus
  subtotal_cents: number
  tax_cents: number
  total_cents: number
  auto_close_at: string | null
  paid_at: string | null
  lines?: OrderLine[]
}

export interface PaymentAttempt {
  id: number
  order_id: number
  method: PaymentMethod
  operator_user_id: number
  amount_cents: number
  status: PaymentStatus
  completed_at: string | null
}

export interface Receipt {
  id: number
  order_id: number
  receipt_number: string
  issued_at: string
}

export interface CatalogItem {
  id: number
  fee_category_id: number
  sku: string
  name: string
  description: string | null
  unit_price_cents: number
  is_active: boolean
}

export interface FeeCategory {
  id: number
  code: string
  label: string
  is_taxable: boolean
  tax_rules?: TaxRule[]
}

export interface TaxRule {
  id: number
  fee_category_id: number
  rate_bps: number
  effective_from: string
  effective_to: string | null
}

export interface BillLine {
  id: number
  bill_id: number
  description: string
  quantity: number
  unit_price_cents: number
  line_total_cents: number
}

export interface Bill {
  id: number
  user_id: number
  type: BillType
  subtotal_cents: number
  tax_cents: number
  total_cents: number
  paid_cents: number
  refunded_cents: number
  status: BillStatus
  issued_on: string
  due_on: string | null
  lines?: BillLine[]
}

export interface BillSchedule {
  id: number
  user_id: number
  schedule_type: string
  amount_cents: number
  status: string
  start_on: string
  end_on: string | null
  next_run_on: string | null
}

export interface Refund {
  id: number
  bill_id: number
  amount_cents: number
  reason_code_id: number
  status: RefundStatus
  notes: string | null
  approved_at: string | null
  completed_at: string | null
}

export interface RefundReasonCode {
  id: number
  code: string
  label: string
}

export interface LedgerEntry {
  id: number
  user_id: number
  bill_id: number | null
  order_id: number | null
  entry_type: LedgerEntryType
  amount_cents: number
  description: string
  created_at: string
}

export interface ReconciliationFlag {
  id: number
  source_type: string
  source_id: number
  status: ReconciliationStatus
  resolved_by: number | null
  resolved_at: string | null
  notes: string | null
}

export interface Appointment {
  id: number
  owner_user_id: number
  resource_type: string
  resource_ref: string | null
  scheduled_start: string
  scheduled_end: string
  status: AppointmentStatus
  notes: string | null
}

export interface RosterImport {
  id: number
  term_id: number
  initiated_by: number
  source_filename: string
  row_count: number
  success_count: number
  error_count: number
  status: RosterImportStatus
  completed_at: string | null
  errors?: Array<{ row: number; field: string; message: string }>
}

// Dashboard summary (role-aware — backend sends only the fields relevant to the caller)
export interface DashboardSummary {
  enrolled_sections?: number
  open_bills?: number
  unread_notifications?: number
  pending_orders?: number
  // teacher
  assigned_sections?: number
  draft_grade_items?: number
  // registrar
  pending_roster_imports?: number
  pending_enrollments?: number
  // admin
  moderation_queue_size?: number
  reconciliation_flags?: number
  circuit_status?: 'closed' | 'open'
}

// Observability
export type ServiceStatus = 'healthy' | 'degraded' | 'down'
export type CircuitState  = 'closed' | 'open' | 'half-open'

export interface HealthStatus {
  status: ServiceStatus
  database: ServiceStatus
  queue: ServiceStatus
  circuit: CircuitState
  error_rate_pct: number
  timestamp: string
}

export interface DiagnosticExport {
  id: number
  initiated_by: number
  file_path: string
  file_size_bytes: number | null
  checksum_sha256: string | null
  status: 'pending' | 'running' | 'completed' | 'failed'
  completed_at: string | null
  generated_at: string
  size_bytes: number
}

export type BackupStatus = 'running' | 'completed' | 'failed' | 'pruned'

export interface BackupJob {
  id: number
  scheduled_for: string
  file_path: string | null
  file_size_bytes: number | null
  checksum_sha256: string | null
  status: BackupStatus
  retention_expires_on: string
  completed_at: string | null
}

export type DrDrillOutcome = 'passed' | 'failed' | 'partial'

export interface DrDrillRecord {
  id: number
  drill_date: string
  operator_user_id: number
  outcome: DrDrillOutcome
  notes: string | null
  created_at: string
}

export interface AdminSettings {
  edit_window_minutes?: number
  order_auto_close_minutes?: number
  penalty_grace_days?: number
  penalty_rate_bps?: number
  fanout_batch_size?: number
  backup_retention_days?: number
  receipt_number_prefix?: string
}

export interface AuditLogEntry {
  id: number
  actor_user_id: number | null
  action: string
  target_type: string
  target_id: number | null
  payload: Record<string, unknown>
  correlation_id: string
  created_at: string
}

// Moderation
export interface ModerationAction {
  id: number
  target_type: 'thread' | 'post' | 'comment'
  target_id: number
  action: 'hide' | 'restore' | 'lock'
  reason: string | null
  moderator_id: number
  created_at: string
}

export interface SensitiveWordCheckResult {
  blocked: boolean
  blocked_terms: Array<{ term: string; start: number; end: number }>
}
