<?php

namespace App;

use App\Traits\HasEncryptedAttributes;
use App\Traits\HasUuid;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable, HasUuid, HasEncryptedAttributes;

    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'username',
        'from_name',
        'banner_location',
        'bandwidth',
        'default_recipient_id',
        'password',
        'two_factor_enabled',
        'two_factor_secret'
    ];

    protected $encrypted = [
        'from_name',
        'two_factor_secret'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'string',
        'default_recipient_id' => 'string',
        'two_factor_enabled' => 'boolean',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'email_verified_at'
    ];

    /**
     * Set the user's username.
     */
    public function setUsernameAttribute($value)
    {
        $this->attributes['username'] = strtolower($value);
    }

    /**
     * Get the user's default email.
     */
    public function getEmailAttribute()
    {
        return $this->defaultRecipient->email;
    }

    /**
     * Get the user's default email verified_at.
     */
    public function getEmailVerifiedAtAttribute()
    {
        return $this->defaultRecipient->email_verified_at;
    }

    /**
     * Set the user's default email verified_at.
     */
    public function setEmailVerifiedAtAttribute($value)
    {
        $this->defaultRecipient->update(['email_verified_at' => $value]);
    }

    /**
     * Set the user's default email.
     */
    public function setDefaultRecipientAttribute($recipient)
    {
        $this->attributes['default_recipient_id'] = $recipient->id;
        $this->setRelation('defaultRecipient', $recipient);
    }

    /**
     * Get the user's bandwidth in MB.
     */
    public function getBandwidthMbAttribute()
    {
        return round($this->bandwidth / 1024 / 1024, 2);
    }

    /**
     * Get the user's default recipient.
     */
    public function defaultRecipient()
    {
        return $this->hasOne(Recipient::class, 'id', 'default_recipient_id');
    }

    /**
     * Get all of the user's email aliases.
     */
    public function aliases()
    {
        return $this->hasMany(Alias::class);
    }

    /**
     * Get all of the user's recipients.
     */
    public function recipients()
    {
        return $this->hasMany(Recipient::class);
    }

    /**
     * Get all of the user's custom domains.
     */
    public function domains()
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * Get all of the user's verified recipients.
     */
    public function verifiedRecipients()
    {
        return $this->recipients()->whereNotNull('email_verified_at');
    }

    /**
     * Get all of the alias recipient pivot rows for the user.
     */
    public function aliasRecipients()
    {
        return $this->hasManyThrough(AliasRecipient::class, Alias::class);
    }

    /**
     * Get all of the user's aliases that are using the default recipient
     */
    public function aliasesUsingDefault()
    {
        return $this->aliases()->whereDoesntHave('recipients');
    }

    public function hasVerifiedDefaultRecipient()
    {
        return ! is_null($this->defaultRecipient->email_verified_at);
    }

    public function totalEmailsForwarded()
    {
        return $this->aliases()->sum('emails_forwarded');
    }

    public function totalEmailsBlocked()
    {
        return $this->aliases()->sum('emails_blocked');
    }

    public function totalEmailsReplied()
    {
        return $this->aliases()->sum('emails_replied');
    }

    public function getBandwidthLimit()
    {
        // TODO check user's limit and return
        return 104857600;
    }

    public function getBandwidthLimitMb()
    {
        return round($this->getBandwidthLimit() / 1024 / 1024, 2);
        ;
    }

    public function nearBandwidthLimit()
    {
        return ($this->bandwidth / $this->getBandwidthLimit()) > 0.9;
    }

    public function hasExceededNewAliasLimit()
    {
        return $this
                ->aliases()
                ->where('created_at', '>=', now()->subHour())
                ->count() >= 10; // TODO update for different plans
    }
}
