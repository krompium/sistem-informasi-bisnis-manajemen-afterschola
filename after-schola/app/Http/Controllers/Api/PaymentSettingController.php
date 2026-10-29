<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentSettingResource;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;

class PaymentSettingController extends Controller
{
    public function show()
    {
        return new PaymentSettingResource(PaymentSetting::current());
    }

    // Endpoint berdiri sendiri: validate() inline (konvensi §5.2).
    // TODO(nanti): interval reminder baru dipakai scheduler SYS-04 (sisa Modul 5).
    public function update(Request $request)
    {
        $validated = $request->validate([
            'due_days' => ['required', 'integer', 'min:1', 'max:90'],
            'reminder_interval_days' => ['required', 'integer', 'min:1', 'max:30'],
            'payment_instructions' => ['nullable', 'string', 'max:2000'],
            'bank_account' => ['nullable', 'string', 'max:500'],
        ]);

        $setting = PaymentSetting::current();
        $setting->update($validated);

        return new PaymentSettingResource($setting);
    }
}
