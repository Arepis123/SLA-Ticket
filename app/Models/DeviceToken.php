<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone that receives push notifications through Firebase Cloud Messaging.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $personal_access_token_id
 * @property string $token
 * @property string $platform
 */
class DeviceToken extends Model
{
    protected $fillable = ['user_id', 'personal_access_token_id', 'token', 'platform'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
