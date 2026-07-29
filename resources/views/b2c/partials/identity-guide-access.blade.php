@if(
    isset($identityAccess)
    && !$identityAccess['approved']
)
    <section
        class="identity-access-card {{
            $identityAccess['blocked']
                ? 'identity-access-blocked'
                : 'identity-access-notice'
        }}"
    >
        <div>
            <strong>
                {{
                    $identityAccess['blocked']
                        ? 'Verificación de identidad requerida'
                        : 'Protege tu cuenta'
                }}
            </strong>

            <p>{{ $identityAccess['message'] }}</p>

            <small>
                Guías generadas:
                {{ $identityAccess['generated_guides'] }}
                de {{ $identityAccess['limit'] }}.
            </small>

            @if($identityAccess['reserved_payments'] > 0)
                <small>
                    Pagos o guías en proceso:
                    {{ $identityAccess['reserved_payments'] }}.
                </small>
            @endif
        </div>

        <a
            href="{{ route('b2c.configuracion') }}#identidad"
            class="identity-access-link"
        >
            {{
                $identityAccess['identity_status']
                    === 'PENDIENTE'
                    ? 'Ver estado'
                    : 'Completar mi identidad'
            }}
        </a>
    </section>
@endif
