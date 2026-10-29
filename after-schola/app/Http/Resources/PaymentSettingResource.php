<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'due_days' => $this->due_days,
            'reminder_interval_days' => $this->reminder_interval_days,
            'payment_instructions' => $this->payment_instructions,
            'bank_account' => $this->bank_account,
        ];
    }
}
