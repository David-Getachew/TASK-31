<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BillScheduleStatus;
use App\Enums\BillStatus;
use App\Enums\BillType;
use App\Enums\LedgerEntryType;
use App\Enums\PenaltyJobStatus;
use App\Models\Bill;
use App\Models\BillLine;
use App\Models\BillSchedule;
use App\Models\FeeCategory;
use App\Models\LedgerEntry;
use App\Models\PenaltyJob;
use App\Models\User;
use App\Support\AuditLogger;
use CampusLearn\Billing\BillScheduleCalculator;
use CampusLearn\Billing\BillScheduleSnapshot;
use CampusLearn\Billing\Money;
use CampusLearn\Billing\PenaltyCalculator;
use CampusLearn\Billing\TaxRuleCalculator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BillingService
{
    public function __construct(
        private readonly TaxRuleCalculator $taxCalculator,
        private readonly PenaltyCalculator $penaltyCalculator,
        private readonly BillScheduleCalculator $scheduleCalculator,
        private readonly AuditLogger $audit,
        private readonly NotificationOrchestrator $notifications,
    ) {
    }

    public function generateInitialBill(User $user, BillSchedule $schedule): Bill
    {
        return DB::transaction(function () use ($user, $schedule): Bill {
            [$bill, $chargeEntry] = $this->buildBillFromSchedule($user, $schedule, BillType::Initial);

            $this->audit->record(null, 'bill.generated', 'bill', $bill->id, [
                'type'       => BillType::Initial->value,
                'total_cents' => $bill->total_cents,
            ]);

            $this->notifications->notify('billing.initial', [$user->id], [
                ':amount'  => number_format($bill->total_cents / 100, 2),
                ':due_on'  => $bill->due_on?->toDateString() ?? '',
            ]);

            return $bill->load('lines');
        });
    }

    public function generateRecurring(User $user, BillSchedule $schedule): Bill
    {
        return DB::transaction(function () use ($user, $schedule): Bill {
            [$bill] = $this->buildBillFromSchedule($user, $schedule, BillType::Recurring);

            $snapshot = new BillScheduleSnapshot(
                status: $schedule->status->value,
                scheduleType: $schedule->schedule_type->value,
                startOn: new \DateTimeImmutable($schedule->start_on->toDateString()),
                endOn: $schedule->end_on ? new \DateTimeImmutable($schedule->end_on->toDateString()) : null,
                lastRunOn: new \DateTimeImmutable(now()->toDateString()),
            );

            $nextRun = $this->scheduleCalculator->nextRunAt($snapshot, new \DateTimeImmutable(now()->toDateString()));

            $updateData = ['next_run_on' => $nextRun ? $nextRun->format('Y-m-d') : null];

            if ($schedule->end_on && now()->toDateString() >= $schedule->end_on->toDateString()) {
                $updateData['status'] = BillScheduleStatus::Closed;
            }

            $schedule->update($updateData);

            $this->audit->record(null, 'bill.generated', 'bill', $bill->id, [
                'type'       => BillType::Recurring->value,
                'total_cents' => $bill->total_cents,
            ]);

            $this->notifications->notify('billing.recurring', [$user->id], [
                ':amount'  => number_format($bill->total_cents / 100, 2),
                ':due_on'  => $bill->due_on?->toDateString() ?? '',
            ]);

            return $bill->load('lines');
        });
    }

    public function generateSupplemental(User $user, array $lineData, string $reason): Bill
    {
        return DB::transaction(function () use ($user, $lineData, $reason): Bill {
            $amount   = Money::ofCents((int) ($lineData['amount_cents'] ?? 0));
            $dueDate  = now()->addDays(30)->toDateString();

            $bill = Bill::create([
                'user_id'         => $user->id,
                'bill_schedule_id' => null,
                'type'            => BillType::Supplemental,
                'subtotal_cents'  => $amount->cents,
                'tax_cents'       => 0,
                'total_cents'     => $amount->cents,
                'paid_cents'      => 0,
                'refunded_cents'  => 0,
                'status'          => BillStatus::Open,
                'issued_on'       => now()->toDateString(),
                'due_on'          => $dueDate,
            ]);

            BillLine::create([
                'bill_id'          => $bill->id,
                'catalog_item_id'  => $lineData['catalog_item_id'] ?? null,
                'description'      => $reason,
                'quantity'         => 1,
                'unit_price_cents' => $amount->cents,
                'tax_rule_snapshot' => null,
                'line_total_cents' => $amount->cents,
            ]);

            LedgerEntry::create([
                'user_id'        => $user->id,
                'bill_id'        => $bill->id,
                'entry_type'     => LedgerEntryType::Charge,
                'amount_cents'   => $amount->cents,
                'description'    => 'Supplemental charge: ' . $reason,
                'correlation_id' => request()->attributes->get('correlation_id', (string) \Illuminate\Support\Str::uuid()),
                'created_at'     => now(),
            ]);

            $this->audit->record(null, 'bill.generated', 'bill', $bill->id, [
                'type'        => BillType::Supplemental->value,
                'total_cents' => $bill->total_cents,
                'reason'      => $reason,
            ]);

            return $bill->load('lines');
        });
    }

    public function applyPenalty(Bill $bill, \DateTimeImmutable $runDate): ?Bill
    {
        return DB::transaction(function () use ($bill, $runDate): ?Bill {
            $idempotencyKey = hash('sha256', $bill->id . ':' . $runDate->format('Y-m-d'));

            $exists = PenaltyJob::where('idempotency_key', $idempotencyKey)->exists();
            if ($exists) {
                return null;
            }

            $graceDays   = config('campuslearn.billing.penalty_grace_days', 10);
            if (!$bill->due_on) {
                return null;
            }
            $today = now()->startOfDay();
            $dueDate = $bill->due_on->copy()->startOfDay();
            if ($dueDate->greaterThanOrEqualTo($today)) {
                return null;
            }
            $daysPastDue = (int) $dueDate->diffInDays($today, false);
            if ($daysPastDue < 0) {
                $daysPastDue = 0;
            }
            if ($daysPastDue < $graceDays) {
                return null;
            }

            $outstanding = Money::ofCents($bill->total_cents - $bill->paid_cents + $bill->refunded_cents);
            $penalty     = $this->penaltyCalculator->compute($outstanding, $daysPastDue);

            if ($penalty->isZero()) {
                return null;
            }

            $dueDays    = config('campuslearn.billing.penalty_bill_due_days', 30);
            $penaltyBill = Bill::create([
                'user_id'         => $bill->user_id,
                'bill_schedule_id' => null,
                'type'            => BillType::Penalty,
                'subtotal_cents'  => $penalty->cents,
                'tax_cents'       => 0,
                'total_cents'     => $penalty->cents,
                'paid_cents'      => 0,
                'refunded_cents'  => 0,
                'status'          => BillStatus::Open,
                'issued_on'       => now()->toDateString(),
                'due_on'          => now()->addDays($dueDays)->toDateString(),
            ]);

            BillLine::create([
                'bill_id'          => $penaltyBill->id,
                'description'      => 'Late penalty on bill #' . $bill->id,
                'quantity'         => 1,
                'unit_price_cents' => $penalty->cents,
                'tax_rule_snapshot' => null,
                'line_total_cents' => $penalty->cents,
            ]);

            LedgerEntry::create([
                'user_id'        => $bill->user_id,
                'bill_id'        => $penaltyBill->id,
                'entry_type'     => LedgerEntryType::Penalty,
                'amount_cents'   => $penalty->cents,
                'description'    => 'Penalty for bill #' . $bill->id,
                'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
                'created_at'     => now(),
            ]);

            PenaltyJob::create([
                'bill_id'         => $bill->id,
                'applied_at'      => now(),
                'amount_cents'    => $penalty->cents,
                'status'          => PenaltyJobStatus::Applied,
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->audit->record(null, 'bill.penalty_applied', 'bill', $penaltyBill->id, [
                'source_bill_id' => $bill->id,
                'penalty_cents'  => $penalty->cents,
            ]);

            $this->notifications->notify('billing.penalty', [$bill->user_id], [
                ':amount'  => number_format($penalty->cents / 100, 2),
                ':due_on'  => $penaltyBill->due_on->toDateString(),
            ]);

            return $penaltyBill;
        });
    }

    public function userBills(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Bill::where('user_id', $user->id)
            ->orderByDesc('issued_on')
            ->paginate($perPage);
    }

    public function adminBills(int $perPage = 20): LengthAwarePaginator
    {
        return Bill::with('user')
            ->orderByDesc('issued_on')
            ->paginate($perPage);
    }

    public function listSchedules(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return BillSchedule::where('user_id', $user->id)->orderBy('start_on')->get();
    }

    public function updateSchedule(User $actor, BillSchedule $schedule, array $data): BillSchedule
    {
        return DB::transaction(function () use ($actor, $schedule, $data): BillSchedule {
            $schedule->update(array_intersect_key($data, array_flip(['status', 'end_on'])));

            $this->audit->record($actor->id, 'bill_schedule.updated', 'bill_schedule', $schedule->id, [
                'changes' => $data,
            ]);

            return $schedule->fresh();
        });
    }

    private function buildBillFromSchedule(User $user, BillSchedule $schedule, BillType $type): array
    {
        $amount   = Money::ofCents($schedule->amount_cents);
        $tax      = Money::zero();
        $snapshot = null;

        $category = $schedule->feeCategory;
        if ($category && $category->is_taxable) {
            $activeTaxRule = $category->taxRules()
                ->where('effective_from', '<=', now()->toDateString())
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()->toDateString()))
                ->first();

            if ($activeTaxRule) {
                $tax = $this->taxCalculator->computeTax($amount, $activeTaxRule->rate_bps);
                $snapshot = [
                    'rate_bps'       => $activeTaxRule->rate_bps,
                    'effective_from' => $activeTaxRule->effective_from->toDateString(),
                    'effective_to'   => $activeTaxRule->effective_to?->toDateString(),
                ];
            }
        }

        $total   = $amount->add($tax);
        $dueDate = now()->addDays(30)->toDateString();

        $bill = Bill::create([
            'user_id'          => $user->id,
            'bill_schedule_id' => $schedule->id,
            'type'             => $type,
            'subtotal_cents'   => $amount->cents,
            'tax_cents'        => $tax->cents,
            'total_cents'      => $total->cents,
            'paid_cents'       => 0,
            'refunded_cents'   => 0,
            'status'           => BillStatus::Open,
            'issued_on'        => now()->toDateString(),
            'due_on'           => $dueDate,
        ]);

        BillLine::create([
            'bill_id'          => $bill->id,
            'description'      => $schedule->feeCategory?->label ?? 'Billing charge',
            'quantity'         => 1,
            'unit_price_cents' => $amount->cents,
            'tax_rule_snapshot' => $snapshot,
            'line_total_cents' => $amount->cents,
        ]);

        $chargeEntry = LedgerEntry::create([
            'user_id'        => $user->id,
            'bill_id'        => $bill->id,
            'entry_type'     => LedgerEntryType::Charge,
            'amount_cents'   => $total->cents,
            'description'    => ucfirst($type->value) . ' charge',
            'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
            'created_at'     => now(),
        ]);

        return [$bill, $chargeEntry];
    }
}
