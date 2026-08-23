<?php

namespace App\Http\Requests\Tenant;

use App\Domain\AI\Conversations\Data\SendConversationMessageData;
use App\Domain\Network\Tenancy\TenantAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SendInternalConversationMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(TenantAccessService::class)->canManageTenant($this->user());
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['message' => is_string($this->input('message')) ? trim($this->input('message')) : $this->input('message')]);
    }

    public function rules(): array
    {
        return ['message' => ['required', 'string', 'max:2000']];
    }

    public function withValidator(Validator $v): void
    {
        $v->after(function (Validator $v) {
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, ['_token', 'message'], true)) {
                    $v->errors()->add($key, 'Este campo no está permitido.');
                }
            }if ($v->errors()->isEmpty()) {
                try {
                    $this->messageData();
                } catch (\InvalidArgumentException) {
                    $v->errors()->add('message', 'El mensaje no cumple el formato permitido.');
                }
            }
        });
    }

    public function messageData(): SendConversationMessageData
    {
        return SendConversationMessageData::from($this->validated('message'));
    }
}
