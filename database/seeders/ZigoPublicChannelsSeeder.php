<?php

namespace Database\Seeders;

use App\Services\Marketing\PublicChannelService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZigoPublicChannelsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            'facebook' => ['label' => 'Zigo Logística', 'url' => config('social.facebook'), 'is_active' => true],
            'instagram' => ['label' => '@zigologistica', 'url' => config('social.instagram'), 'is_active' => true],
            'tiktok' => ['label' => 'zigologistica', 'url' => config('social.tiktok'), 'is_active' => true],
            'whatsapp' => ['label' => 'WhatsApp', 'url' => null, 'is_active' => false],
            'commercial_phone' => ['label' => 'Teléfono comercial', 'url' => null, 'is_active' => false],
            'support_phone' => ['label' => 'Teléfono soporte', 'url' => null, 'is_active' => false],
            'commercial_email' => ['label' => 'Correo comercial', 'url' => null, 'is_active' => false],
            'support_email' => ['label' => 'Correo soporte', 'url' => null, 'is_active' => false],
        ];

        foreach (PublicChannelService::CHANNELS as $index => $channel) {
            DB::table('zigo_public_channels')->updateOrInsert(
                ['channel' => $channel],
                [
                    'label' => $rows[$channel]['label'],
                    'value' => null,
                    'url' => $rows[$channel]['url'],
                    'is_active' => $rows[$channel]['is_active'],
                    'sort_order' => ($index + 1) * 10,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
