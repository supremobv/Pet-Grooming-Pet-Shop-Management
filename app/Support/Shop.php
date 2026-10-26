<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Shop
{
    public static function settings(): array
    {
        if (is_array($cached = request()->attributes->get('shop.settings'))) {
            return $cached;
        }
        $settings = array_merge(['name' => 'Paw & Polish', 'tagline' => 'Good care. Happy tails.', 'email' => 'hello@example.com', 'phone' => '', 'address' => '', 'currency' => 'PHP', 'tax_rate' => 0, 'open_time' => '09:00', 'close_time' => '18:00', 'open_days' => ['1', '2', '3', '4', '5', '6'], 'receipt_footer' => 'Thank you for caring with us. See you and your best friend soon!', 'logo' => null], json_decode(DB::table('settings')->where('id', 1)->value('data') ?? '{}', true));
        request()->attributes->set('shop.settings', $settings);

        return $settings;
    }

    public static function money(int|float $cents): string
    {
        return self::settings()['currency'].' '.number_format($cents / 100, 2);
    }

    public static function cents(string|int|float $value): int
    {
        return (int) round((float) $value * 100);
    }

    public static function audit(string $action, string $entity, ?int $id = null, array $meta = []): void
    {
        DB::table('audit_logs')->insert(['user_id' => auth()->id(), 'action' => $action, 'entity' => $entity, 'entity_id' => $id, 'metadata' => json_encode($meta), 'ip' => request()->ip(), 'created_at' => now()]);
    }
}
