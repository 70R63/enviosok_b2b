<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\UpdatePublicChannelsRequest;
use App\Models\ZigoPublicChannel;
use App\Services\Marketing\PublicChannelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CrmPublicChannelController extends Controller
{
    public function index(): View
    {
        $stored = ZigoPublicChannel::query()->get()->keyBy('channel');
        $channels = collect(PublicChannelService::CHANNELS)->mapWithKeys(
            fn (string $key): array => [$key => $stored->get($key)]
        );

        return view('crm.marketing.public-channels', compact('channels'));
    }

    public function update(UpdatePublicChannelsRequest $request): RedirectResponse
    {
        $channels = $request->validated('channels');

        DB::transaction(function () use ($channels): void {
            foreach (PublicChannelService::CHANNELS as $key) {
                $data = $channels[$key];
                $value = $this->nullable($data['value'] ?? null);
                $url = $this->nullable($data['url'] ?? null);

                if (in_array($key, PublicChannelService::SOCIAL_CHANNELS, true)
                    && $value !== null
                    && filter_var($value, FILTER_VALIDATE_URL)) {
                    $url = $value;
                    $value = null;
                }

                ZigoPublicChannel::query()->updateOrCreate(
                    ['channel' => $key],
                    [
                        'label' => $this->nullable($data['label'] ?? null),
                        'value' => $value,
                        'url' => $url,
                        'is_active' => (bool) ($data['is_active'] ?? false),
                        'sort_order' => (int) $data['sort_order'],
                    ]
                );
            }
        });

        return redirect()->route('crm.marketing.public-channels.index')
            ->with('success', 'Canales públicos actualizados correctamente.');
    }

    private function nullable(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
