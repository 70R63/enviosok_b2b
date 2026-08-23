<?php

namespace App\Http\Requests\Tenant;

use App\Domain\AI\Handoff\Data\HumanConversationMessageData;
use App\Domain\Network\Tenancy\TenantAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SendHumanConversationMessageRequest extends FormRequest
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, ['_token', 'message'], true)) {
                    $validator->errors()->add($key, 'Este campo no está permitido.');
                }
            } if ($validator->errors()->isEmpty()) {
                try {
                    $this->messageData();
                } catch (\InvalidArgumentException) {
                    $validator->errors()->add('message', 'El mensaje no cumple el formato permitido.');
                }
            }
        });
    }

    public function messageData(): HumanConversationMessageData
    {
        return HumanConversationMessageData::from($this->validated('message'));
    }
}
