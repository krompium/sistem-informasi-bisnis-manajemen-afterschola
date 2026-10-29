<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    protected $fillable = ['due_days', 'reminder_interval_days', 'payment_instructions', 'bank_account'];

    /** Baris tunggal; dibuat dengan default kolom bila belum ada. */
    public static function current(): self
    {
        // fresh(): memuat default kolom dan menghindari wasRecentlyCreated (Resource akan membalas 201).
        return static::first() ?? static::create([])->fresh();
    }
}
