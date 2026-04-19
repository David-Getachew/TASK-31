<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Enums\OrderStatus;
use App\Enums\OrderTimelineEvent as OrderTimelineEventEnum;
use App\Enums\PaymentStatus;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderTimelineEvent;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Support\AuditLogger;
use CampusLearn\Orders\OrderStateMachine;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PaymentService
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly ReceiptService $receiptService,
        private readonly AuditLogger $audit,
        private readonly NotificationOrchestrator $notifications,
    ) {
    }

    public function initiate(Order $order, string $method, User $operator, ?string $kioskId = null): PaymentAttempt
    {
        return DB::transaction(function () use ($order, $method, $operator, $kioskId): PaymentAttempt {
            if ($order->status !== OrderStatus::PendingPayment) {
                throw new RuntimeException('Order is not in pending_payment state.');
            }

            $this->stateMachine->transition($order->status, OrderTimelineEventEnum::PaymentInitiated);

            $attempt = PaymentAttempt::create([
                'order_id'        => $order->id,
                'method'          => $method,
                'operator_user_id' => $operator->id,
                'kiosk_id'        => $kioskId,
                'amount_cents'    => $order->total_cents,
                'status'          => PaymentStatus::Pending,
                'completed_at'    => null,
            ]);

            OrderTimelineEvent::create([
                'order_id'      => $order->id,
                'event'         => OrderTimelineEventEnum::PaymentInitiated,
                'actor_user_id' => $operator->id,
                'payload'       => ['attempt_id' => $attempt->id],
                'created_at'    => now(),
            ]);

            return $attempt;
        });
    }

    public function complete(Order $order, PaymentAttempt $attempt, User $operator): Order
    {
        return DB::transaction(function () use ($order, $attempt, $operator): Order {
            $newStatus = $this->stateMachine->transition($order->status, OrderTimelineEventEnum::Paid);

            if ($attempt->status !== PaymentStatus::Pending) {
                throw new RuntimeException('Payment attempt is not in pending state.');
            }

            $attempt->update([
                'status'       => PaymentStatus::Succeeded,
                'completed_at' => now(),
            ]);

            $order->update([
                'status'  => $newStatus,
                'paid_at' => now(),
            ]);

            LedgerEntry::create([
                'user_id'     => $order->user_id,
                'order_id'    => $order->id,
                'entry_type'  => LedgerEntryType::Payment,
                'amount_cents' => -$order->total_cents,
                'description' => 'Order payment #' . $order->id,
                'correlation_id' => request()->attributes->get('correlation_id', (string) \Illuminate\Support\Str::uuid()),
                'created_at'  => now(),
            ]);

            OrderTimelineEvent::create([
                'order_id'      => $order->id,
                'event'         => OrderTimelineEventEnum::Paid,
                'actor_user_id' => $operator->id,
                'payload'       => ['attempt_id' => $attempt->id],
                'created_at'    => now(),
            ]);

            $this->receiptService->generate($order->fresh());

            $this->audit->record($operator->id, 'order.paid', 'order', $order->id, [
                'amount_cents' => $order->total_cents,
                'method'       => $attempt->method,
            ]);

            $this->notifications->notify('billing.paid', [$order->user_id], [
                ':order_id' => (string) $order->id,
                ':amount'   => number_format($order->total_cents / 100, 2),
            ]);

            return $order->fresh('lines', 'receipt');
        });
    }
}
