<?php

namespace App\Http\Requests\CRM;

use App\Services\Marketing\PublicChannelService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePublicChannelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channels' => ['required', 'array'],
            'channels.*' => ['required', 'array:label,value,url,is_active,sort_order'],
            'channels.*.label' => ['nullable', 'string', 'max:120'],
            'channels.*.value' => ['nullable', 'string', 'max:255'],
            'channels.*.url' => ['nullable', 'string', 'max:255'],
            'channels.*.is_active' => ['nullable', 'boolean'],
            'channels.*.sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $submitted = array_keys((array) $this->input('channels', []));
            if (array_diff($submitted, PublicChannelService::CHANNELS)
                || array_diff(PublicChannelService::CHANNELS, $submitted)) {
                $validator->errors()->add('channels', 'El catálogo de canales enviado no es válido.');
                return;
            }

            $service = app(PublicChannelService::class);
            foreach ((array) $this->input('channels') as $key => $channel) {
                foreach (['label', 'value', 'url'] as $field) {
                    $input = (string) ($channel[$field] ?? '');
                    if ($input !== strip_tags($input)) {
                        $validator->errors()->add("channels.$key.$field", 'No se permite HTML.');
                    }
                }

                $value = trim((string) ($channel['value'] ?? ''));
                $url = trim((string) ($channel['url'] ?? ''));

                if (in_array($key, PublicChannelService::SOCIAL_CHANNELS, true)) {
                    if ($url !== '' && !$service->isAllowedSocialUrl($key, $url)) {
                        $validator->errors()->add("channels.$key.url", 'Usa HTTPS y el dominio oficial de esta red social.');
                    }
                    if ($value !== '' && filter_var($value, FILTER_VALIDATE_URL)) {
                        if (!$service->isAllowedSocialUrl($key, $value)) {
                            $validator->errors()->add("channels.$key.value", 'Usa HTTPS y el dominio oficial de esta red social.');
                        }
                    } elseif ($url === '' && $value !== '' && $service->normalizedUrl($key, $value) === null) {
                        $validator->errors()->add("channels.$key.value", 'Ingresa un usuario válido.');
                    }
                } elseif ($url !== '') {
                    $validator->errors()->add("channels.$key.url", 'Este canal debe configurarse mediante el campo valor.');
                }

                if ($key === 'whatsapp' && $value !== '' && !preg_match('/^\+?[0-9\s().-]{7,25}$/', $value)) {
                    $validator->errors()->add("channels.$key.value", 'Ingresa un número de WhatsApp válido.');
                }
                if (in_array($key, ['commercial_phone', 'support_phone'], true)
                    && $value !== '' && !preg_match('/^\+?[0-9\s().-]{7,25}$/', $value)) {
                    $validator->errors()->add("channels.$key.value", 'Ingresa un teléfono válido.');
                }
                if (in_array($key, ['commercial_email', 'support_email'], true)
                    && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $validator->errors()->add("channels.$key.value", 'Ingresa un correo válido.');
                }
            }
        });
    }
}
