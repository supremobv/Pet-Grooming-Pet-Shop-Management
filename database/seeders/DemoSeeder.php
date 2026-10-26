<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Demo data is allowed only in local/testing.');
        }
        if (DB::table('customers')->exists() || DB::table('products')->exists() || DB::table('sales')->exists() || DB::table('services')->exists()) {
            throw new \RuntimeException('Demo seeding requires an empty shop database. Existing data will not be changed.');
        }
        $admin = User::where('role', 'admin')->where('active', true)->first();
        if (! $admin) {
            throw new \RuntimeException('First create an administrator with app:admin.');
        }
        DB::transaction(function () use ($admin) {
            $supplier = DB::table('suppliers')->insertGetId(['name' => 'Happy Pet Supply', 'email' => 'orders@example.test', 'phone' => '0917 555 0100', 'address' => 'Sample supplier address', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            $serviceIds = [];
            foreach ([['Full groom & style', 90, 85000], ['Bath & brush', 60, 45000], ['Nail trim & care', 20, 15000], ['Puppy’s first pamper', 60, 55000]] as [$name,$duration,$price]) {
                $serviceIds[] = DB::table('services')->insertGetId(['name' => $name, 'duration' => $duration, 'price_cents' => $price, 'description' => 'Gentle, attentive care tailored to your pet.', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach ([['Emma Santos', 'Milo', 'Dog', 'Shih Tzu'], ['Liam Reyes', 'Luna', 'Cat', 'British Shorthair'], ['Sofia Cruz', 'Cooper', 'Dog', 'Golden Retriever'], ['Noah Garcia', 'Bella', 'Dog', 'Poodle'], ['Olivia Lim', 'Oliver', 'Cat', 'Ragdoll']] as $i => [$owner,$name,$species,$breed]) {
                $customer = DB::table('customers')->insertGetId(['name' => $owner, 'phone' => '0917 555 010'.($i + 1), 'email' => strtolower(explode(' ', $owner)[0]).'@example.test', 'address' => 'Sample customer address', 'notes' => 'Sample record — replace before real operation.', 'created_at' => now(), 'updated_at' => now()]);
                $pet = DB::table('pets')->insertGetId(['customer_id' => $customer, 'name' => $name, 'species' => $species, 'breed' => $breed, 'birth_date' => '2022-05-14', 'weight' => 5 + $i * 2, 'medical_notes' => $i === 0 ? 'Sensitive skin; use gentle shampoo.' : null, 'behavior_notes' => 'Prefers a calm introduction.', 'vaccination_due' => today()->addMonths(3), 'created_at' => now(), 'updated_at' => now()]);
                if ($i < 4) {
                    DB::table('appointments')->insert(['pet_id' => $pet, 'service_id' => $serviceIds[$i], 'groomer_id' => $admin->id, 'starts_at' => today()->setTime(9 + $i * 2, 0), 'ends_at' => today()->setTime(9 + $i * 2, 0)->addMinutes([90, 60, 20, 60][$i]), 'status' => ['completed', 'in_progress', 'checked_in', 'booked'][$i], 'price_cents' => [85000, 45000, 15000, 55000][$i], 'notes' => 'Sample appointment', 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            foreach ([['Natural salmon kibble', 'FOOD-001', 'Food', 125000, 85000, 24], ['Gentle oatmeal shampoo', 'CARE-001', 'Grooming', 38000, 21000, 3], ['Everyday walking harness', 'ACC-001', 'Accessories', 65000, 35000, 12], ['Chicken training bites', 'FOOD-002', 'Food', 18000, 9500, 4], ['Soft slicker brush', 'CARE-002', 'Grooming', 25000, 13000, 18], ['Cozy donut pet bed', 'ACC-002', 'Accessories', 145000, 80000, 8], ['Clumping cat litter', 'CAT-001', 'Cat essentials', 42000, 25000, 16], ['Rope & tug toy', 'TOY-001', 'Toys', 15000, 7000, 2]] as [$name,$sku,$category,$price,$cost,$stock]) {
                $id = DB::table('products')->insertGetId(['name' => $name, 'sku' => $sku, 'category' => $category, 'supplier_id' => $supplier, 'price_cents' => $price, 'cost_cents' => $cost, 'stock' => $stock, 'reorder_level' => 5, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('stock_movements')->insert(['product_id' => $id, 'user_id' => $admin->id, 'quantity' => $stock, 'reason' => 'Demo opening stock', 'created_at' => now()]);
            }
        });
    }
}
