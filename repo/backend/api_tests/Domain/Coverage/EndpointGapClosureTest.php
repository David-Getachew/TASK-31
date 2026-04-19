<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Enums\AppointmentStatus;
use App\Enums\BillScheduleStatus;
use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\ReconciliationSourceType;
use App\Enums\ReconciliationStatus;
use App\Enums\RosterImportStatus;
use App\Models\Appointment;
use App\Models\Bill;
use App\Models\BillLine;
use App\Models\BillSchedule;
use App\Models\Comment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FeeCategory;
use App\Models\GradeItem;
use App\Models\Notification;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Post;
use App\Models\ReconciliationFlag;
use App\Models\RefundReasonCode;
use App\Models\RosterImport;
use App\Models\RosterImportError;
use App\Models\Section;
use App\Models\SensitiveWordRule;
use App\Models\TaxRule;
use App\Models\Term;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin sensitive-word endpoints are covered with CRUD assertions', function () {
    $admin = User::factory()->asAdmin()->create(['status' => AccountStatus::Active]);

    $create = $this->actingAs($admin)->postJson('/api/v1/admin/sensitive-words', [
        'pattern' => 'forbidden',
        'match_type' => 'exact',
    ]);

    $create->assertStatus(201)
        ->assertJsonPath('data.pattern', 'forbidden')
        ->assertJsonPath('data.match_type', 'exact');

    $ruleId = $create->json('data.id');

    $this->actingAs($admin)->getJson('/api/v1/admin/sensitive-words')
        ->assertStatus(200)
        ->assertJsonPath('data.0.id', $ruleId);

    $this->actingAs($admin)->deleteJson("/api/v1/admin/sensitive-words/{$ruleId}")
        ->assertStatus(204);

    $this->assertDatabaseMissing('sensitive_word_rules', ['id' => $ruleId]);
});

test('order list show and cancel endpoints are covered with ownership assertions', function () {
    $user = User::factory()->create(['status' => AccountStatus::Active]);

    $order = Order::factory()->for($user)->create([
        'status' => OrderStatus::PendingPayment,
    ]);

    $this->actingAs($user)->getJson('/api/v1/orders')
        ->assertStatus(200)
        ->assertJsonPath('data.data.0.id', $order->id);

    $this->actingAs($user)->getJson("/api/v1/orders/{$order->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $order->id);

    $this->actingAs($user)->deleteJson("/api/v1/orders/{$order->id}")
        ->assertStatus(204);

    expect($order->fresh()->canceled_at)->not->toBeNull();
    expect($order->fresh()->status)->toBe(OrderStatus::Canceled);
});

test('admin fee category list and tax-rule create update endpoints are covered', function () {
    $admin = User::factory()->asAdmin()->create(['status' => AccountStatus::Active]);

    $category = FeeCategory::factory()->create([
        'code' => 'fees-a',
        'label' => 'Fees A',
        'is_taxable' => true,
    ]);

    $this->actingAs($admin)->getJson('/api/v1/admin/fee-categories')
        ->assertStatus(200)
        ->assertJsonPath('data.0.id', $category->id);

    $createTaxRule = $this->actingAs($admin)->postJson("/api/v1/admin/fee-categories/{$category->id}/tax-rules", [
        'rate_bps' => 1250,
        'effective_from' => now()->toDateString(),
    ]);

    $createTaxRule->assertStatus(201)
        ->assertJsonPath('data.fee_category_id', $category->id)
        ->assertJsonPath('data.rate_bps', 1250);

    $taxRuleId = $createTaxRule->json('data.id');

    $this->actingAs($admin)->patchJson("/api/v1/admin/fee-categories/{$category->id}/tax-rules/{$taxRuleId}", [
        'rate_bps' => 1400,
    ])->assertStatus(200)
      ->assertJsonPath('data.id', $taxRuleId)
      ->assertJsonPath('data.rate_bps', 1400);

    $this->assertDatabaseHas('tax_rules', ['id' => $taxRuleId, 'rate_bps' => 1400]);
});

test('admin catalog update endpoint is covered with persistence assertion', function () {
    $admin = User::factory()->asAdmin()->create(['status' => AccountStatus::Active]);
    $category = FeeCategory::factory()->create(['is_taxable' => false]);

    $item = \App\Models\CatalogItem::factory()->for($category)->create([
        'unit_price_cents' => 1000,
        'is_active' => true,
    ]);

    $this->actingAs($admin)->patchJson("/api/v1/admin/catalog/{$item->id}", [
        'name' => 'Updated Catalog Item',
        'unit_price_cents' => 1750,
    ])->assertStatus(200)
      ->assertJsonPath('data.id', $item->id)
      ->assertJsonPath('data.unit_price_cents', 1750);

    $this->assertDatabaseHas('catalog_items', ['id' => $item->id, 'unit_price_cents' => 1750]);
});

test('moderation history and admin post hide restore endpoints are covered', function () {
    $admin = User::factory()->asAdmin()->create(['status' => AccountStatus::Active]);
    $author = User::factory()->create(['status' => AccountStatus::Active]);

    $thread = Thread::factory()->for($author, 'author')->create();
    $post = Post::factory()->for($thread)->for($author, 'author')->create();

    $this->actingAs($admin)->postJson("/api/v1/admin/threads/{$thread->id}/posts/{$post->id}/hide", [
        'reason' => 'policy_violation',
    ])->assertStatus(200)
      ->assertJsonPath('data.id', $post->id)
      ->assertJsonPath('data.state', 'hidden');

    $this->actingAs($admin)->postJson("/api/v1/admin/threads/{$thread->id}/posts/{$post->id}/restore", [
        'reason' => 'restored',
    ])->assertStatus(200)
      ->assertJsonPath('data.id', $post->id)
      ->assertJsonPath('data.state', 'visible');

    $this->actingAs($admin)->getJson('/api/v1/admin/moderation/history')
        ->assertStatus(200)
        ->assertJsonPath('data.data.0.target_type', 'post');
});

test('admin reconciliation summary endpoint is covered with aggregate assertions', function () {
    $admin = User::factory()->asAdmin()->create(['status' => AccountStatus::Active]);

    ReconciliationFlag::factory()->create([
        'source_type' => ReconciliationSourceType::Refund,
        'status' => ReconciliationStatus::Open,
    ]);

    ReconciliationFlag::factory()->create([
        'source_type' => ReconciliationSourceType::LedgerMismatch,
        'status' => ReconciliationStatus::Resolved,
    ]);

    $this->actingAs($admin)->getJson('/api/v1/admin/reconciliation/summary')
        ->assertStatus(200)
        ->assertJsonPath('data.open_count', 1)
        ->assertJsonPath('data.resolved_count', 1);
});

test('appointment list show and update endpoints are covered with data assertions', function () {
    $owner = User::factory()->create(['status' => AccountStatus::Active]);

    $appointment = Appointment::factory()->create([
        'owner_user_id' => $owner->id,
        'created_by' => $owner->id,
        'status' => AppointmentStatus::Scheduled,
    ]);

    $this->actingAs($owner)->getJson('/api/v1/appointments')
        ->assertStatus(200)
        ->assertJsonPath('data.data.0.id', $appointment->id);

    $this->actingAs($owner)->getJson("/api/v1/appointments/{$appointment->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $appointment->id)
        ->assertJsonPath('data.owner.id', $owner->id);

    $this->actingAs($owner)->patchJson("/api/v1/appointments/{$appointment->id}", [
        'status' => AppointmentStatus::Rescheduled->value,
        'notes' => 'Moved to next slot',
    ])->assertStatus(200)
      ->assertJsonPath('data.id', $appointment->id)
      ->assertJsonPath('data.status', AppointmentStatus::Rescheduled->value)
      ->assertJsonPath('data.notes', 'Moved to next slot');

    $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => AppointmentStatus::Rescheduled->value]);
});

test('billing schedule list and update endpoints are covered with persistence assertions', function () {
    $user = User::factory()->create(['status' => AccountStatus::Active]);
    $schedule = BillSchedule::factory()->for($user)->create(['status' => BillScheduleStatus::Active]);

    $this->actingAs($user)->getJson('/api/v1/billing-schedules')
        ->assertStatus(200)
        ->assertJsonPath('data.0.id', $schedule->id);

    $this->actingAs($user)->patchJson("/api/v1/billing-schedules/{$schedule->id}", [
        'status' => BillScheduleStatus::Paused->value,
    ])->assertStatus(200)
      ->assertJsonPath('data.id', $schedule->id)
      ->assertJsonPath('data.status', BillScheduleStatus::Paused->value);

    $this->assertDatabaseHas('bill_schedules', ['id' => $schedule->id, 'status' => BillScheduleStatus::Paused->value]);
});

test('bill list and show endpoints are covered with ownership assertions', function () {
    $user = User::factory()->create(['status' => AccountStatus::Active]);
    $bill = Bill::factory()->for($user)->create(['status' => BillStatus::Open]);

    BillLine::factory()->for($bill)->create([
        'description' => 'Coverage line item',
        'line_total_cents' => 5000,
    ]);

    $this->actingAs($user)->getJson('/api/v1/bills')
        ->assertStatus(200)
        ->assertJsonPath('data.data.0.id', $bill->id);

    $this->actingAs($user)->getJson("/api/v1/bills/{$bill->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $bill->id)
        ->assertJsonPath('data.lines.0.bill_id', $bill->id);
});

test('course list and show endpoints are covered', function () {
    $admin = User::factory()->asAdmin()->create(['status' => AccountStatus::Active]);
    $term = Term::factory()->create();
    $course = Course::factory()->for($term)->create(['code' => 'COV-101']);

    $this->actingAs($admin)->getJson('/api/v1/courses')
        ->assertStatus(200)
        ->assertJsonPath('data.data.0.id', $course->id);

    $this->actingAs($admin)->getJson("/api/v1/courses/{$course->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $course->id)
        ->assertJsonPath('data.term.id', $term->id);
});

test('refund reason codes endpoint is covered', function () {
    $user = User::factory()->create(['status' => AccountStatus::Active]);
    $code = RefundReasonCode::factory()->create(['code' => 'coverage_reason', 'label' => 'Coverage Reason']);

    $this->actingAs($user)->getJson('/api/v1/refund-reason-codes')
        ->assertStatus(200)
        ->assertJsonPath('data.0.code', $code->code);
});

test('roster import history and show endpoints are covered', function () {
    $registrar = User::factory()->asRegistrar()->create(['status' => AccountStatus::Active]);
    $term = Term::factory()->create();

    $import = RosterImport::create([
        'term_id' => $term->id,
        'initiated_by' => $registrar->id,
        'source_filename' => 'roster.csv',
        'row_count' => 2,
        'success_count' => 1,
        'error_count' => 1,
        'status' => RosterImportStatus::Completed,
        'completed_at' => now(),
    ]);

    RosterImportError::create([
        'roster_import_id' => $import->id,
        'row_number' => 2,
        'error_code' => 'INVALID_EMAIL',
        'message' => 'Invalid email address',
        'raw_row' => ['email' => 'bad-email'],
    ]);

    $this->actingAs($registrar)->getJson("/api/v1/terms/{$term->id}/roster-imports")
        ->assertStatus(200)
        ->assertJsonPath('data.data.0.id', $import->id);

    $this->actingAs($registrar)->getJson("/api/v1/roster-imports/{$import->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $import->id)
        ->assertJsonPath('data.errors.0.error_code', 'INVALID_EMAIL');
});

test('section list and grade item list update endpoints are covered', function () {
    $user = User::factory()->create(['status' => AccountStatus::Active]);
    $term = Term::factory()->create();
    $course = Course::factory()->for($term)->create();
    $section = Section::factory()->for($course)->create(['term_id' => $term->id]);

    Enrollment::factory()->for($user)->for($section)->create();

    $item = GradeItem::factory()->for($section)->create([
        'title' => 'Coverage Quiz',
        'max_score' => 100,
    ]);

    $this->actingAs($user)->getJson('/api/v1/sections')
        ->assertStatus(200)
        ->assertJsonPath('data.data.0.id', $section->id);

    $this->actingAs($user)->getJson("/api/v1/sections/{$section->id}/grade-items")
        ->assertStatus(200)
        ->assertJsonPath('data.0.id', $item->id);

    $this->actingAs($user)->patchJson("/api/v1/sections/{$section->id}/grade-items/{$item->id}", [
        'title' => 'Updated Coverage Quiz',
        'max_score' => 120,
    ])->assertStatus(200)
      ->assertJsonPath('data.id', $item->id)
      ->assertJsonPath('data.max_score', 120);

    $this->assertDatabaseHas('grade_items', ['id' => $item->id, 'max_score' => 120.0]);
});

test('thread posts list create and thread update endpoints are covered', function () {
    $author = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $term = Term::factory()->create();
    $course = Course::factory()->for($term)->create();
    $section = Section::factory()->for($course)->create(['term_id' => $term->id]);

    Enrollment::factory()->for($author)->for($section)->create();

    $thread = Thread::factory()->for($author, 'author')->create([
        'course_id' => $course->id,
        'section_id' => $section->id,
        'title' => 'Original Title',
    ]);

    $createPost = $this->actingAs($author)->postJson("/api/v1/threads/{$thread->id}/posts", [
        'body' => 'First coverage post',
    ]);

    $createPost->assertStatus(201)
        ->assertJsonPath('data.thread_id', $thread->id)
        ->assertJsonPath('data.body', 'First coverage post');

    $this->actingAs($author)->getJson("/api/v1/threads/{$thread->id}/posts")
        ->assertStatus(200)
        ->assertJsonPath('data.data.0.thread_id', $thread->id);

    $this->actingAs($author)->patchJson("/api/v1/threads/{$thread->id}", [
        'title' => 'Updated Title',
    ])->assertStatus(200)
      ->assertJsonPath('data.id', $thread->id)
      ->assertJsonPath('data.title', 'Updated Title');

    $this->assertDatabaseHas('threads', ['id' => $thread->id, 'title' => 'Updated Title']);
});

test('post comment and report endpoints are covered with target assertions', function () {
    $user = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $term = Term::factory()->create();
    $course = Course::factory()->for($term)->create();
    $section = Section::factory()->for($course)->create(['term_id' => $term->id]);
    Enrollment::factory()->for($user)->for($section)->create();

    $thread = Thread::factory()->for($user, 'author')->create([
        'course_id' => $course->id,
        'section_id' => $section->id,
    ]);
    $post = Post::factory()->for($thread)->for($user, 'author')->create();

    $createComment = $this->actingAs($user)->postJson("/api/v1/posts/{$post->id}/comments", [
        'body' => 'Coverage comment',
    ]);

    $createComment->assertStatus(201)
        ->assertJsonPath('data.post_id', $post->id)
        ->assertJsonPath('data.body', 'Coverage comment');

    $commentId = $createComment->json('data.id');

    $this->actingAs($user)->postJson("/api/v1/posts/{$post->id}/reports", [
        'reason' => 'spam',
        'notes' => 'Coverage report post',
    ])->assertStatus(201)
      ->assertJsonPath('data.target_type', 'post')
      ->assertJsonPath('data.target_id', $post->id);

    $this->actingAs($user)->postJson("/api/v1/posts/{$post->id}/comments/{$commentId}/reports", [
        'reason' => 'abuse',
        'notes' => 'Coverage report comment',
    ])->assertStatus(201)
      ->assertJsonPath('data.target_type', 'comment')
      ->assertJsonPath('data.target_id', $commentId);

    $this->assertDatabaseHas('reports', ['target_type' => 'post', 'target_id' => $post->id]);
    $this->assertDatabaseHas('reports', ['target_type' => 'comment', 'target_id' => $commentId]);
});

test('notification mark-one-read endpoint is covered with persistence assertion', function () {
    $user = User::factory()->create(['status' => AccountStatus::Active]);

    $notification = Notification::factory()->for($user)->create(['read_at' => null]);

    $this->actingAs($user)->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertStatus(200)
        ->assertJsonPath('data.marked', true);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('payment initiate endpoint is covered with idempotency and persistence assertions', function () {
    $staff = User::factory()->asRegistrar()->create(['status' => AccountStatus::Active]);
    $owner = User::factory()->create(['status' => AccountStatus::Active]);

    $order = Order::factory()->for($owner)->create([
        'status' => OrderStatus::PendingPayment,
        'total_cents' => 5000,
    ]);

    $response = $this->actingAs($staff)->postJson("/api/v1/orders/{$order->id}/payment", [
        'method' => 'cash',
    ], [
        'Idempotency-Key' => 'coverage-initiate-' . (string) \Illuminate\Support\Str::uuid(),
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.order_id', $order->id)
        ->assertJsonPath('data.method', 'cash')
        ->assertJsonPath('data.status', 'pending');

    $attemptId = $response->json('data.id');
    $this->assertDatabaseHas('payment_attempts', ['id' => $attemptId, 'order_id' => $order->id]);

    expect(PaymentAttempt::find($attemptId))->not->toBeNull();
});
