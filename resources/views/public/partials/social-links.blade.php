@inject('publicChannelService', 'App\Services\Marketing\PublicChannelService')
@php($publicChannels = $publicChannelService->active()->keyBy('channel'))

<div class="zigo-social-follow">
    <h4>Síguenos</h4>
    <div class="zigo-social-links">
        @if($publicChannels->has('facebook'))
        <a class="zigo-social-link"
           href="{{ $publicChannels->get('facebook')['url'] }}"
           target="_blank"
           rel="noopener noreferrer"
           aria-label="Seguir a ZIGO en Facebook">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.52 1.49-3.91 3.77-3.91 1.09 0 2.23.2 2.23.2V8.6h-1.25c-1.24 0-1.63.77-1.63 1.56v1.9h2.77l-.44 2.91h-2.33V22C18.34 21.24 22 17.08 22 12.06Z"/>
            </svg>
            <span>Facebook</span>
        </a>
        @endif
        @if($publicChannels->has('instagram'))
        <a class="zigo-social-link"
           href="{{ $publicChannels->get('instagram')['url'] }}"
           target="_blank"
           rel="noopener noreferrer"
           aria-label="Seguir a ZIGO en Instagram">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2Zm-.2 2A3.6 3.6 0 0 0 4 7.6v8.8A3.6 3.6 0 0 0 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6A3.6 3.6 0 0 0 16.4 4H7.6Zm9.65 1.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/>
            </svg>
            <span>Instagram</span>
        </a>
        @endif
        @if($publicChannels->has('tiktok'))
        <a class="zigo-social-link"
           href="{{ $publicChannels->get('tiktok')['url'] }}"
           target="_blank"
           rel="noopener noreferrer"
           aria-label="Seguir a ZIGO en TikTok">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M16.6 2c.35 2.35 1.65 3.75 4.4 3.9v3.2a8.73 8.73 0 0 1-4.35-1.27v6.1a6.07 6.07 0 1 1-5.23-6.02v3.25a2.9 2.9 0 1 0 2.03 2.77V2h3.15Z"/>
            </svg>
            <span>TikTok</span>
        </a>
        @endif
    </div>

    @php($contactChannels = $publicChannels->only(['whatsapp', 'commercial_phone', 'support_phone', 'commercial_email', 'support_email']))
    @if($contactChannels->isNotEmpty())
        <h4>Contacto</h4>
        <div class="zigo-public-contact">
            @foreach($contactChannels as $channel)
                <a href="{{ $channel['url'] }}"
                   @if($channel['channel'] === 'whatsapp') target="_blank" rel="noopener noreferrer" @endif>
                    {{ $channel['label'] }}
                </a>
            @endforeach
        </div>
    @endif
</div>
