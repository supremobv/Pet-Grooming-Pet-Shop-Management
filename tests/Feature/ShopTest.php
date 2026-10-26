<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $customer;

    private int $pet;

    private int $service;

    private int $product;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') === 'mysql' && ! str_ends_with(config('database.connections.mysql.database'), '_test')) {
            throw new \RuntimeException('MySQL tests require a database name ending in _test.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(8, 0));
        $this->admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->customer = DB::table('customers')->insertGetId(['name' => 'Test Owner', 'phone' => '09170000000', 'created_at' => now(), 'updated_at' => now()]);
        $this->pet = DB::table('pets')->insertGetId(['name' => 'Test Pet', 'customer_id' => $this->customer, 'species' => 'Dog', 'created_at' => now(), 'updated_at' => now()]);
        $this->service = DB::table('services')->insertGetId(['name' => 'Bath', 'duration' => 60, 'price_cents' => 50000, 'created_at' => now(), 'updated_at' => now()]);
        $this->product = DB::table('products')->insertGetId(['name' => 'Shampoo', 'sku' => 'SH-001', 'category' => 'Care', 'price_cents' => 20000, 'cost_cents' => 10000, 'stock' => 5, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->admin);
    }

    private function booking(array $changes = []): array
    {
        return array_replace(['pet_id' => $this->pet, 'service_id' => $this->service, 'groomer_id' => $this->admin->id, 'starts_at' => '2026-11-02T10:00', 'notes' => 'Gentle care'], $changes);
    }

    private function cart(array $changes = []): array
    {
        return array_replace(['checkout_key' => (string) Str::uuid(), 'items' => [['product_id' => $this->product, 'quantity' => 2]], 'discount' => '0', 'tendered' => '400', 'payment_method' => 'cash'], $changes);
    }

    public function test_admin_pages_render(): void
    {
        foreach (['/', '/account', '/customers', '/customers/create', '/customers/'.$this->customer.'/edit', '/customers/'.$this->customer, '/pets/'.$this->pet, '/pets', '/pets/create', '/products', '/products/create', '/services', '/suppliers', '/stock', '/appointments', '/appointments/create', '/checkout', '/sales', '/settings', '/users', '/audit', '/reports'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_customer_crud_search_and_archive_preserves_history(): void
    {
        $this->post('/customers', ['name' => 'Alice', 'phone' => '123', 'email' => 'alice@example.test'])->assertSessionHasNoErrors();
        $id = DB::table('customers')->where('name', 'Alice')->value('id');
        $this->put('/customers/'.$id, ['name' => 'Alice Updated', 'phone' => '456'])->assertSessionHasNoErrors();
        $this->get('/customers?q=Alice')->assertSee('Alice Updated');
        $this->post('/customers/'.$id.'/archive')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', ['id' => $id, 'active' => false]);
        $this->get('/customers')->assertDontSee('Alice Updated');
        $this->get('/customers?archived=1')->assertSee('Alice Updated');
    }

    public function test_staff_roles_are_enforced_on_server(): void
    {
        foreach (['receptionist', 'groomer', 'cashier', 'inventory'] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->actingAs($u)->get('/settings')->assertForbidden();
            $this->post('/users', ['name' => 'Intruder'])->assertForbidden();
            $this->post('/sales/1/refund', ['refund_reason' => 'attack'])->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'groomer']))->get('/customers')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'inventory']))->get('/sales')->assertForbidden();
    }

    public function test_disabled_user_cannot_sign_in_or_keep_session(): void
    {
        $u = User::factory()->create(['active' => false, 'password' => 'ValidPassword!123']);
        $this->actingAs($u)->get('/')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/login', ['email' => $u->email, 'password' => 'ValidPassword!123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_and_security_headers(): void
    {
        auth()->logout();
        $this->post('/login', ['email' => $this->admin->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->admin->update(['password' => 'ValidPassword!123']);
        $this->post('/login', ['email' => $this->admin->email, 'password' => 'ValidPassword!123'])->assertRedirect('/');
        $this->get('/')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_login_rate_limit(): void
    {
        auth()->logout();
        for ($i = 0; $i < 6; $i++) {
            $response = $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'wrong']);
        }
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many sign-in attempts', session('errors')->first('email'));
    }

    public function test_booking_prevents_conflicts_and_out_of_hours(): void
    {
        $this->post('/appointments', $this->booking())->assertSessionHasNoErrors();
        $this->post('/appointments', $this->booking(['starts_at' => '2026-11-02T10:30']))->assertSessionHasErrors('starts_at');
        $this->post('/appointments', $this->booking(['starts_at' => '2026-11-02T18:00']))->assertSessionHasErrors('starts_at');
        $this->post('/appointments', $this->booking(['starts_at' => '2026-11-01T10:00']))->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_pet_cannot_be_double_booked_with_different_groomer(): void
    {
        $this->post('/appointments', $this->booking())->assertSessionHasNoErrors();
        $g = User::factory()->create(['role' => 'groomer']);
        $this->post('/appointments', $this->booking(['groomer_id' => $g->id]))->assertSessionHasErrors('starts_at');
    }

    public function test_appointment_lifecycle_and_assigned_groomer_access(): void
    {
        $g = User::factory()->create(['role' => 'groomer']);
        $other = User::factory()->create(['role' => 'groomer']);
        $this->post('/appointments', $this->booking(['groomer_id' => $g->id]))->assertSessionHasNoErrors();
        $id = DB::table('appointments')->value('id');
        $this->post('/appointments/'.$id.'/status', ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->post('/appointments/'.$id.'/status', ['status' => 'checked_in'])->assertSessionHasNoErrors();
        $this->actingAs($other)->get('/appointments')->assertDontSee('Test Pet');
        $this->post('/appointments/'.$id.'/status', ['status' => 'in_progress'])->assertForbidden();
        $this->actingAs($g)->get('/appointments')->assertSee('Test Pet');
        $this->post('/appointments/'.$id.'/status', ['status' => 'in_progress', 'grooming_notes' => 'Calm visit'])->assertSessionHasNoErrors();
        $this->post('/appointments/'.$id.'/status', ['status' => 'ready'])->assertSessionHasNoErrors();
        $this->post('/appointments/'.$id.'/status', ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->post('/appointments/'.$id.'/status', ['status' => 'completed'])->assertSessionHasNoErrors();
    }

    public function test_checkout_is_atomic_uses_server_prices_and_is_idempotent(): void
    {
        $cart = $this->cart(['items' => [['product_id' => $this->product, 'quantity' => 2, 'price_cents' => 1]]]);
        $response = $this->post('/checkout', $cart)->assertSessionHasNoErrors();
        $id = DB::table('sales')->value('id');
        $response->assertRedirect('/sales/'.$id);
        $this->post('/checkout', $cart)->assertRedirect('/sales/'.$id);
        $this->assertDatabaseHas('products', ['id' => $this->product, 'stock' => 3]);
        $this->assertDatabaseHas('sales', ['total_cents' => 40000]);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->get('/sales/'.$id)->assertOk()->assertSee('Shampoo');
    }

    public function test_insufficient_stock_and_underpayment_rollback(): void
    {
        $this->post('/checkout', $this->cart(['items' => [['product_id' => $this->product, 'quantity' => 6]], 'tendered' => 1200]))->assertSessionHasErrors('items');
        $this->post('/checkout', $this->cart(['tendered' => 399]))->assertSessionHasErrors('tendered');
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseHas('products', ['stock' => 5]);
    }

    public function test_duplicate_product_lines_are_aggregated(): void
    {
        $this->post('/checkout', $this->cart(['items' => [['product_id' => $this->product, 'quantity' => 3], ['product_id' => $this->product, 'quantity' => 3]], 'tendered' => 1200]))->assertSessionHasErrors('items');
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_tax_discount_and_non_cash_reference_validation(): void
    {
        DB::table('settings')->insert(['id' => 1, 'data' => json_encode(['tax_rate' => 12])]);
        $this->post('/checkout', $this->cart(['discount' => 100, 'tendered' => 336, 'payment_method' => 'card']))->assertSessionHasErrors('payment_reference');
        $this->post('/checkout', $this->cart(['discount' => 100, 'tendered' => 336, 'payment_method' => 'card', 'payment_reference' => 'TERMINAL-001']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sales', ['subtotal_cents' => 40000, 'discount_cents' => 10000, 'tax_cents' => 3600, 'total_cents' => 33600]);
    }

    public function test_grooming_checkout_uses_owner_and_blocks_double_billing(): void
    {
        $this->post('/appointments', $this->booking())->assertSessionHasNoErrors();
        $id = DB::table('appointments')->value('id');
        DB::table('appointments')->where('id', $id)->update(['status' => 'ready']);
        $cart = $this->cart(['items' => [], 'appointment_id' => $id, 'tendered' => 500]);
        $this->post('/checkout', $cart)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sales', ['appointment_id' => $id, 'customer_id' => $this->customer, 'total_cents' => 50000]);
        $this->post('/checkout', [...$cart, 'checkout_key' => (string) Str::uuid()])->assertSessionHasErrors('appointment_id');
    }

    public function test_refund_returns_stock_exactly_once(): void
    {
        $this->post('/checkout', $this->cart())->assertSessionHasNoErrors();
        $id = DB::table('sales')->value('id');
        $this->post('/sales/'.$id.'/refund', ['refund_reason' => 'Returned unopened'])->assertSessionHasNoErrors();
        $this->post('/sales/'.$id.'/refund', ['refund_reason' => 'Duplicate refund'])->assertSessionHasErrors('refund_reason');
        $this->assertDatabaseHas('products', ['stock' => 5]);
        $this->assertDatabaseHas('sales', ['status' => 'refunded']);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_stock_adjustment_disallows_negative_balance(): void
    {
        $this->post('/stock', ['product_id' => $this->product, 'quantity' => -6, 'reason' => 'Damaged goods'])->assertSessionHasErrors('quantity');
        $this->post('/stock', ['product_id' => $this->product, 'quantity' => 10, 'reason' => 'New delivery'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['stock' => 15]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'stock_adjusted']);
    }

    public function test_branding_saves_and_rejects_executable_uploads(): void
    {
        $data = [...Shop::settings(), 'name' => 'My Pet Shop'];
        unset($data['logo']);
        $this->post('/settings', $data)->assertSessionHasNoErrors();
        $this->get('/')->assertSee('My Pet Shop');
        $this->post('/settings', [...$data, 'logo' => UploadedFile::fake()->create('attack.php', 1, 'application/x-php')])->assertSessionHasErrors('logo');
    }

    public function test_admin_cannot_demote_self(): void
    {
        $this->put('/users/'.$this->admin->id, ['name' => 'Owner', 'email' => $this->admin->email, 'role' => 'cashier', 'active' => 1])->assertSessionHasErrors('role');
        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'role' => 'admin']);
    }

    public function test_output_is_escaped_and_invalid_data_is_friendly(): void
    {
        $this->post('/customers', ['name' => '<script>alert(1)</script>', 'phone' => '123'])->assertSessionHasNoErrors();
        $this->get('/customers')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->post('/pets', ['name' => 'Missing owner'])->assertSessionHasErrors(['customer_id', 'species']);
    }

    public function test_reports_export_and_refunded_totals(): void
    {
        $this->post('/checkout', $this->cart())->assertSessionHasNoErrors();
        $this->get('/reports?from=2026-11-01&to=2026-11-30')->assertOk()->assertSee('400.00');
        $this->get('/reports?from=2026-11-01&to=2026-11-30&export=csv')->assertDownload('sales-2026-11-01-2026-11-30.csv');
        $this->post('/sales/'.DB::table('sales')->value('id').'/refund', ['refund_reason' => 'Return accepted'])->assertSessionHasNoErrors();
        $this->get('/reports?from=2026-11-01&to=2026-11-30')->assertOk();
    }

    public function test_csrf_rejects_a_real_request_without_token(): void
    {
        $this->app['env'] = 'local';
        $this->post('/customers', ['name' => 'Forged', 'phone' => '123'])->assertStatus(419);
        $this->assertDatabaseMissing('customers', ['name' => 'Forged']);
    }

    public function test_large_catalog_and_empty_lines_checkout(): void
    {
        $items = [];
        for ($i = 0; $i < 110; $i++) {
            $items[] = ['product_id' => $this->product, 'quantity' => 0];
        }$items[] = ['product_id' => $this->product, 'quantity' => 1];
        $this->post('/checkout', $this->cart(['items' => $items, 'tendered' => 200]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['stock' => 4]);
    }

    public function test_currency_is_locked_after_first_sale(): void
    {
        $this->post('/checkout', $this->cart())->assertSessionHasNoErrors();
        $data = [...Shop::settings(), 'currency' => 'USD'];
        unset($data['logo']);
        $this->post('/settings', $data)->assertSessionHasErrors('currency');
    }

    public function test_branding_upload_is_reencoded_and_receipt_keeps_old_business_details(): void
    {
        $data = Shop::settings();
        unset($data['logo']);
        $this->post('/settings', [...$data, 'name' => 'Original Brand', 'logo' => UploadedFile::fake()->image('brand.jpg', 64, 64)])->assertSessionHasNoErrors();
        $logo = json_decode(DB::table('settings')->value('data'), true)['logo'];
        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}\.png$/', $logo);
        try {
            $this->post('/checkout', $this->cart())->assertSessionHasNoErrors();
            $id = DB::table('sales')->value('id');
            $this->post('/settings', [...$data, 'name' => 'New Brand', 'remove_logo' => 1])->assertSessionHasNoErrors();
            $this->get('/sales/'.$id)->assertSee('Original Brand');
            $this->get('/sales/'.$id.'/logo')->assertOk()->assertHeader('Content-Type', 'image/png');
        } finally {
            $path = storage_path('app/logos/'.$logo);
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_admin_can_create_staff_and_reset_password_without_exposing_hash(): void
    {
        $this->post('/users', ['name' => 'Front desk', 'email' => 'desk@example.test', 'role' => 'receptionist', 'active' => 1, 'password' => 'TemporaryPass!123', 'password_confirmation' => 'TemporaryPass!123'])->assertSessionHasNoErrors();
        $u = User::where('email', 'desk@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('TemporaryPass!123', $u->password));
        $this->get('/users')->assertDontSee($u->password);
        $this->put('/users/'.$u->id, ['name' => $u->name, 'email' => $u->email, 'role' => 'cashier', 'active' => 1, 'password' => 'ReplacementPass!123', 'password_confirmation' => 'ReplacementPass!123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('ReplacementPass!123', $u->fresh()->password));
    }

    public function test_own_password_change_requires_current_password(): void
    {
        $this->admin->update(['password' => 'OriginalPassword!123']);
        $this->post('/account/password', ['current_password' => 'wrong', 'password' => 'ReplacementPass!123', 'password_confirmation' => 'ReplacementPass!123'])->assertSessionHasErrors('current_password');
        $this->post('/account/password', ['current_password' => 'OriginalPassword!123', 'password' => 'ReplacementPass!123', 'password_confirmation' => 'ReplacementPass!123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('ReplacementPass!123',$this->admin->fresh()->password));
    }

    public function test_cashier_overview_does_not_expose_appointment_care_data(): void
    {
        $this->post('/appointments',$this->booking())->assertSessionHasNoErrors();
        $this->actingAs(User::factory()->create(['role' => 'cashier']))->get('/')->assertOk()->assertDontSee('Test Pet')->assertDontSee('Test Owner');
    }
}
