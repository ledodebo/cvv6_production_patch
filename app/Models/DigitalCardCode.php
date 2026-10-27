<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Exception;

class DigitalCardCode extends Model
{
    use HasFactory;

    protected $table = 'digital_card_codes';

    protected $fillable = [
        'order_id',
        'order_detail_id',
        'user_id',
        'card_code',
        'pin',
        'raw_data',
        'revealed_at',
        'delivered_at',
    ];

    protected $casts = [
        'revealed_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /**
     * Get dedicated encrypter using CARD_VAULT_KEY or fallback to APP_KEY
     */
    protected static function getEncrypter(): \Illuminate\Encryption\Encrypter
    {
        $vaultKey = config('digital_purchase.vault_key') ?: config('app.key');
        if (str_starts_with($vaultKey, 'base64:')) {
            $key = base64_decode(substr($vaultKey, 7));
        } else {
            $key = hash('sha256', (string)$vaultKey, true);
        }
        $cipher = config('app.cipher', 'AES-256-CBC');
        return new \Illuminate\Encryption\Encrypter($key, $cipher);
    }

    /**
     * Encrypt card_code on save
     */
    public function setCardCodeAttribute($value): void
    {
        $this->attributes['card_code'] = !empty($value) ? static::getEncrypter()->encryptString((string)$value) : null;
    }

    /**
     * Decrypt card_code on read
     */
    public function getCardCodeAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            return static::getEncrypter()->decryptString($value);
        } catch (\Throwable $e) {
            try {
                return Crypt::decryptString($value);
            } catch (\Throwable $e2) {
                return $value; // Fallback in case stored as plain text
            }
        }
    }

    /**
     * Encrypt pin on save
     */
    public function setPinAttribute($value): void
    {
        $this->attributes['pin'] = !empty($value) ? static::getEncrypter()->encryptString((string)$value) : null;
    }

    /**
     * Decrypt pin on read
     */
    public function getPinAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            return static::getEncrypter()->decryptString($value);
        } catch (\Throwable $e) {
            try {
                return Crypt::decryptString($value);
            } catch (\Throwable $e2) {
                return $value;
            }
        }
    }

    /**
     * Relationship: Order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Relationship: OrderDetail
     */
    public function orderDetail(): BelongsTo
    {
        return $this->belongsTo(OrderDetail::class, 'order_detail_id');
    }

    /**
     * Relationship: User (Customer)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
