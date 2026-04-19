<?php

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Enums\RoleName;
use App\Models\Bill;
use App\Models\CatalogItem;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FeeCategory;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Post;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Section;
use App\Models\Term;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class E2eTestSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::updateOrCreate(
            ['email' => 'student@example.com'],
            [
                'name' => 'E2E Student',
                'password' => Hash::make('Password1234!'),
                'locale' => 'en',
                'status' => 'active',
            ],
        );

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'E2E Admin',
                'password' => Hash::make('AdminPass999!'),
                'locale' => 'en',
                'status' => 'active',
            ],
        );

        $teacher = User::updateOrCreate(
            ['email' => 'teacher@example.com'],
            [
                'name' => 'E2E Teacher',
                'password' => Hash::make('TeacherPass999!'),
                'locale' => 'en',
                'status' => 'active',
            ],
        );

        $registrar = User::updateOrCreate(
            ['email' => 'registrar@example.com'],
            [
                'name' => 'E2E Registrar',
                'password' => Hash::make('RegistrarPass999!'),
                'locale' => 'en',
                'status' => 'active',
            ],
        );

        $studentRole = Role::firstOrCreate(['name' => RoleName::Student->value], ['label' => 'Student']);
        $teacherRole = Role::firstOrCreate(['name' => RoleName::Teacher->value], ['label' => 'Teacher']);
        $registrarRole = Role::firstOrCreate(['name' => RoleName::Registrar->value], ['label' => 'Registrar']);
        $adminRole = Role::firstOrCreate(['name' => RoleName::Administrator->value], ['label' => 'Administrator']);

        RoleAssignment::updateOrCreate(
            [
                'user_id' => $student->id,
                'role_id' => $studentRole->id,
                'scope_type' => 'global',
                'scope_id' => null,
            ],
            [
                'granted_at' => now(),
                'revoked_at' => null,
            ],
        );

        RoleAssignment::updateOrCreate(
            [
                'user_id' => $admin->id,
                'role_id' => $adminRole->id,
                'scope_type' => 'global',
                'scope_id' => null,
            ],
            [
                'granted_at' => now(),
                'revoked_at' => null,
            ],
        );

        RoleAssignment::updateOrCreate(
            [
                'user_id' => $teacher->id,
                'role_id' => $teacherRole->id,
                'scope_type' => 'global',
                'scope_id' => null,
            ],
            [
                'granted_at' => now(),
                'revoked_at' => null,
            ],
        );

        RoleAssignment::updateOrCreate(
            [
                'user_id' => $registrar->id,
                'role_id' => $registrarRole->id,
                'scope_type' => 'global',
                'scope_id' => null,
            ],
            [
                'granted_at' => now(),
                'revoked_at' => null,
            ],
        );

        $term = Term::firstOrCreate(
            ['name' => 'E2E Term'],
            [
                'starts_on' => now()->subMonth()->format('Y-m-d'),
                'ends_on' => now()->addMonths(3)->format('Y-m-d'),
                'status' => 'active',
            ],
        );

        $course = Course::firstOrCreate(
            ['code' => 'E2E101'],
            [
                'term_id' => $term->id,
                'title' => 'E2E Course',
                'description' => 'Course for end-to-end tests',
                'status' => 'active',
            ],
        );

        if ($course->term_id === null) {
            $course->term_id = $term->id;
            $course->save();
        }

        $section = Section::firstOrCreate(
            [
                'course_id' => $course->id,
                'term_id' => $term->id,
                'section_code' => 'E2E-A',
            ],
            [
                'capacity' => 40,
                'status' => 'active',
            ],
        );

        Enrollment::updateOrCreate(
            [
                'user_id' => $student->id,
                'section_id' => $section->id,
            ],
            [
                'status' => EnrollmentStatus::Enrolled,
                'enrolled_at' => now()->subDays(5),
                'withdrawn_at' => null,
            ],
        );

        $thread = Thread::firstOrCreate(
            [
                'course_id' => $course->id,
                'section_id' => $section->id,
                'author_id' => $student->id,
                'title' => 'E2E Thread',
            ],
            [
                'thread_type' => 'discussion',
                'qa_enabled' => false,
                'body' => 'Seeded discussion thread for e2e tests.',
                'state' => 'visible',
            ],
        );

        Post::firstOrCreate(
            [
                'thread_id' => $thread->id,
                'author_id' => $student->id,
                'body' => 'Seeded post for e2e reply visibility.',
            ],
            [
                'parent_post_id' => null,
                'state' => 'visible',
            ],
        );

        $feeCategory = FeeCategory::firstOrCreate(
            ['code' => 'E2E_FEE'],
            ['label' => 'E2E Fee Category', 'is_taxable' => true],
        );

        $catalogItem = CatalogItem::firstOrCreate(
            ['sku' => 'E2E-SKU-001'],
            [
                'fee_category_id' => $feeCategory->id,
                'name' => 'E2E Catalog Item',
                'description' => 'Catalog item for e2e tests',
                'unit_price_cents' => 15000,
                'is_active' => true,
            ],
        );

        $order = Order::firstOrCreate(
            [
                'user_id' => $student->id,
                'status' => 'pending_payment',
            ],
            [
                'subtotal_cents' => 15000,
                'tax_cents' => 2250,
                'total_cents' => 17250,
                'auto_close_at' => now()->addHour(),
            ],
        );

        OrderLine::firstOrCreate(
            [
                'order_id' => $order->id,
                'catalog_item_id' => $catalogItem->id,
            ],
            [
                'quantity' => 1,
                'unit_price_cents' => 15000,
                'tax_rule_snapshot' => ['rate_bps' => 1500],
                'line_total_cents' => 15000,
            ],
        );

        Bill::firstOrCreate(
            [
                'user_id' => $student->id,
                'type' => 'initial',
            ],
            [
                'subtotal_cents' => 10000,
                'tax_cents' => 1500,
                'total_cents' => 11500,
                'paid_cents' => 11500,
                'refunded_cents' => 0,
                'status' => 'paid',
                'issued_on' => now()->subDays(7)->format('Y-m-d'),
                'due_on' => now()->addDays(23)->format('Y-m-d'),
                'paid_at' => now()->subDays(2),
            ],
        );

        if (Notification::where('user_id', $student->id)->count() === 0) {
            Notification::create([
                'user_id' => $student->id,
                'category' => 'announcements',
                'type' => 'announcement.new',
                'title' => 'Welcome to CampusLearn',
                'body' => 'Your student account is ready.',
                'payload' => [],
                'read_at' => null,
            ]);

            Notification::create([
                'user_id' => $student->id,
                'category' => 'billing',
                'type' => 'billing.bill_generated',
                'title' => 'New bill available',
                'body' => 'A new bill has been generated.',
                'payload' => [],
                'read_at' => null,
            ]);
        }
    }
}
