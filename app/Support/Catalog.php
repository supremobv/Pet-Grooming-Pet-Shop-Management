<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Catalog
{
    public static function definitions(): array
    {
        return [
            'customers' => ['title' => 'Customers', 'singular' => 'customer', 'subtitle' => 'The people behind every happy pet.', 'fields' => ['name' => ['Full name', 'text', 'required|string|max:120'], 'email' => ['Email', 'email', 'nullable|email|max:160'], 'phone' => ['Phone', 'tel', 'required|string|max:40'], 'address' => ['Address', 'text', 'nullable|string|max:255'], 'notes' => ['Notes', 'textarea', 'nullable|string|max:2000']], 'columns' => ['name' => 'Customer', 'phone' => 'Phone', 'email' => 'Email']],
            'pets' => ['title' => 'Pets', 'singular' => 'pet', 'subtitle' => 'Care starts with knowing each pet.', 'fields' => ['name' => ['Pet name', 'text', 'required|string|max:120'], 'customer_id' => ['Owner', 'customers', 'required|exists:customers,id'], 'species' => ['Species', 'species', 'required|in:Dog,Cat,Rabbit,Bird,Other'], 'breed' => ['Breed', 'text', 'nullable|string|max:120'], 'birth_date' => ['Date of birth', 'date', 'nullable|date|before_or_equal:today'], 'weight' => ['Weight (kg)', 'number', 'nullable|numeric|min:0|max:9999'], 'vaccination_due' => ['Vaccination due', 'date', 'nullable|date'], 'medical_notes' => ['Medical / allergy notes', 'textarea', 'nullable|string|max:3000'], 'behavior_notes' => ['Handling / behavior notes', 'textarea', 'nullable|string|max:3000']], 'columns' => ['name' => 'Pet', 'owner' => 'Owner', 'species' => 'Species', 'breed' => 'Breed', 'vaccination_due' => 'Vaccination due']],
            'services' => ['title' => 'Grooming services', 'singular' => 'service', 'subtitle' => 'Thoughtful care, with a clear price.', 'fields' => ['name' => ['Service name', 'text', 'required|string|max:120'], 'description' => ['Description', 'textarea', 'nullable|string|max:2000'], 'duration' => ['Duration (minutes)', 'number', 'required|integer|min:5|max:480'], 'price' => ['Price', 'number', 'required|numeric|min:0|max:1000000']], 'columns' => ['name' => 'Service', 'duration' => 'Minutes', 'price_cents' => 'Price']],
            'products' => ['title' => 'Products', 'singular' => 'product', 'subtitle' => 'Everyday essentials for their best life.', 'fields' => ['name' => ['Product name', 'text', 'required|string|max:120'], 'sku' => ['SKU / barcode', 'text', 'required|string|max:80'], 'category' => ['Category', 'text', 'required|string|max:100'], 'supplier_id' => ['Supplier', 'suppliers', 'nullable|exists:suppliers,id'], 'price' => ['Selling price', 'number', 'required|numeric|min:0|max:1000000'], 'cost' => ['Unit cost', 'number', 'required|numeric|min:0|max:1000000'], 'reorder_level' => ['Low-stock threshold', 'number', 'required|integer|min:0|max:1000000']], 'columns' => ['name' => 'Product', 'sku' => 'SKU', 'category' => 'Category', 'price_cents' => 'Price', 'stock' => 'In stock', 'reorder_level' => 'Alert at']],
            'suppliers' => ['title' => 'Suppliers', 'singular' => 'supplier', 'subtitle' => 'Good partners keep your shelves ready.', 'fields' => ['name' => ['Supplier name', 'text', 'required|string|max:120'], 'email' => ['Email', 'email', 'nullable|email|max:160'], 'phone' => ['Phone', 'tel', 'nullable|string|max:40'], 'address' => ['Address', 'text', 'nullable|string|max:255']], 'columns' => ['name' => 'Supplier', 'phone' => 'Phone', 'email' => 'Email']],
        ];
    }

    public static function definition(string $module): array
    {
        abort_unless(isset(self::definitions()[$module]), 404);

        return self::definitions()[$module];
    }

    public static function options(): array
    {
        return ['customers' => DB::table('customers')->where('active', true)->orderBy('name')->pluck('name', 'id'), 'suppliers' => DB::table('suppliers')->where('active', true)->orderBy('name')->pluck('name', 'id'), 'species' => collect(['Dog' => 'Dog', 'Cat' => 'Cat', 'Rabbit' => 'Rabbit', 'Bird' => 'Bird', 'Other' => 'Other'])];
    }
}
