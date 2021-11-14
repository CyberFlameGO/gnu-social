<?php

declare(strict_types = 1);

// {{{ License

// This file is part of GNU social - https://www.gnu.org/software/social
//
// GNU social is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// GNU social is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with GNU social.  If not, see <http://www.gnu.org/licenses/>.

// }}}

namespace App\Entity;

use App\Core\Cache;
use App\Core\DB\DB;
use App\Core\Entity;
use App\Core\UserRoles;
use App\Util\Common;
use DateTimeInterface;
use Exception;
use libphonenumber\PhoneNumber;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Entity for users
 *
 * @category  DB
 * @package   GNUsocial
 *
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet Inc.
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2009-2014 Free Software Foundation, Inc http://www.fsf.org
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2020-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class LocalUser extends Entity implements UserInterface
{
    // {{{ Autocode
    // @codeCoverageIgnoreStart
    private int $id;
    private string $nickname;
    private ?string $password;
    private ?string $outgoing_email;
    private ?string $incoming_email;
    private ?bool $is_email_verified;
    private ?string $timezone;
    private ?PhoneNumber $phone_number;
    private ?int $sms_carrier;
    private ?string $sms_email;
    private ?string $uri;
    private ?bool $auto_subscribe_back;
    private ?int $subscription_policy;
    private ?bool $is_stream_private;
    private DateTimeInterface $created;
    private DateTimeInterface $modified;

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setNickname(string $nickname): self
    {
        $this->nickname = $nickname;
        return $this;
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setOutgoingEmail(?string $outgoing_email): self
    {
        $this->outgoing_email = $outgoing_email;
        return $this;
    }

    public function getOutgoingEmail(): ?string
    {
        return $this->outgoing_email;
    }

    public function setIncomingEmail(?string $incoming_email): self
    {
        $this->incoming_email = $incoming_email;
        return $this;
    }

    public function getIncomingEmail(): ?string
    {
        return $this->incoming_email;
    }

    public function setIsEmailVerified(?bool $is_email_verified): self
    {
        $this->is_email_verified = $is_email_verified;
        return $this;
    }

    public function getIsEmailVerified(): ?bool
    {
        return $this->is_email_verified;
    }

    public function setTimezone(?string $timezone): self
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setPhoneNumber(?PhoneNumber $phone_number): self
    {
        $this->phone_number = $phone_number;
        return $this;
    }

    public function getPhoneNumber(): ?PhoneNumber
    {
        return $this->phone_number;
    }

    public function setSmsCarrier(?int $sms_carrier): self
    {
        $this->sms_carrier = $sms_carrier;
        return $this;
    }

    public function getSmsCarrier(): ?int
    {
        return $this->sms_carrier;
    }

    public function setSmsEmail(?string $sms_email): self
    {
        $this->sms_email = $sms_email;
        return $this;
    }

    public function getSmsEmail(): ?string
    {
        return $this->sms_email;
    }

    public function setUri(?string $uri): self
    {
        $this->uri = $uri;
        return $this;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function setAutoSubscribeBack(?bool $auto_subscribe_back): self
    {
        $this->auto_subscribe_back = $auto_subscribe_back;
        return $this;
    }

    public function getAutoSubscribeBack(): ?bool
    {
        return $this->auto_subscribe_back;
    }

    public function setSubscriptionPolicy(?int $subscription_policy): self
    {
        $this->subscription_policy = $subscription_policy;
        return $this;
    }

    public function getSubscriptionPolicy(): ?int
    {
        return $this->subscription_policy;
    }

    public function setIsStreamPrivate(?bool $is_stream_private): self
    {
        $this->is_stream_private = $is_stream_private;
        return $this;
    }

    public function getIsStreamPrivate(): ?bool
    {
        return $this->is_stream_private;
    }

    public function setCreated(DateTimeInterface $created): self
    {
        $this->created = $created;
        return $this;
    }

    public function getCreated(): DateTimeInterface
    {
        return $this->created;
    }

    public function setModified(DateTimeInterface $modified): self
    {
        $this->modified = $modified;
        return $this;
    }

    public function getModified(): DateTimeInterface
    {
        return $this->modified;
    }

    // @codeCoverageIgnoreEnd
    // }}} Autocode

    // {{{ Authentication
    /**
     * Returns the salt that was originally used to encode the password.
     * BCrypt and Argon2 generate their own salts
     */
    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * Removes sensitive data from the user.
     *
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     */
    public function eraseCredentials()
    {
    }

    /**
     * When authenticating, check a user's password in a timing safe
     * way. Will update the password by rehashing if deemed necessary
     */
    public function checkPassword(string $password_plain_text): bool
    {
        // Timing safe password verification
        if (password_verify($password_plain_text, $this->password)) {
            // Update old formats
            if (password_needs_rehash(
                $this->password,
                self::algoNameToConstant(Common::config('security', 'algorithm')),
                Common::config('security', 'options'),
            )
            ) {
                // @codeCoverageIgnoreStart
                $this->changePassword(null, $password_plain_text, override: true);
                // @codeCoverageIgnoreEnd
            }
            return true;
        }
        return false;
    }

    public function changePassword(?string $old_password_plain_text, string $new_password_plain_text, bool $override = false): bool
    {
        if ($override || $this->checkPassword($old_password_plain_text)) {
            $this->setPassword(self::hashPassword($new_password_plain_text));
            DB::flush();
            return true;
        }
        return false;
    }

    public static function hashPassword(string $password)
    {
        $algorithm = self::algoNameToConstant(Common::config('security', 'algorithm'));
        $options   = Common::config('security', 'options');

        return password_hash($password, $algorithm, $options);
    }

    /**
     * Public for testing
     */
    public static function algoNameToConstant(string $algo)
    {
        switch ($algo) {
        case 'bcrypt':
        case 'argon2i':
        case 'argon2d':
        case 'argon2id':
            $c = 'PASSWORD_' . mb_strtoupper($algo);
            if (\defined($c)) {
                return \constant($c);
            }
            // fallthrough
            // no break
        default:
            throw new Exception('Unsupported or unsafe hashing algorithm requested');
        }
    }
    // }}} Authentication

    public function getActor()
    {
        return DB::find('actor', ['id' => $this->id]);
    }

    /**
     * Returns the roles granted to the user
     */
    public function getRoles()
    {
        return UserRoles::toArray($this->getActor()->getRoles());
    }

    /**
     * Returns the username used to authenticate the user. Part of the Symfony UserInterface
     */
    public function getUsername()
    {
        return $this->nickname;
    }

    public static function getByNickname(string $nickname): ?self
    {
        return Cache::get("user-nickname-{$nickname}", fn () => DB::findOneBy('local_user', ['nickname' => $nickname]));
    }

    /**
     * @return self Returns self if email found
     */
    public static function getByEmail(string $email): ?self
    {
        $key = str_replace('@', '-', $email);
        return Cache::get("user-email-{$key}", fn () => DB::findOneBy('local_user', ['or' => ['outgoing_email' => $email, 'incoming_email' => $email]]));
    }

    public static function schemaDef(): array
    {
        return [
            'name'        => 'local_user',
            'description' => 'local users, bots, etc',
            'fields'      => [
                'id'                  => ['type' => 'int',          'foreign key' => true, 'target' => 'Actor.id', 'multiplicity' => 'one to one', 'not null' => true, 'description' => 'foreign key to actor table'],
                'nickname'            => ['type' => 'varchar',      'not null' => true,    'length' => 64, 'description' => 'nickname or username, foreign key to actor'],
                'password'            => ['type' => 'varchar',      'length' => 191,       'description' => 'salted password, can be null for users with federated authentication'],
                'outgoing_email'      => ['type' => 'varchar',      'length' => 191,       'description' => 'email address for password recovery, notifications, etc.'],
                'incoming_email'      => ['type' => 'varchar',      'length' => 191,       'description' => 'email address for post-by-email'],
                'is_email_verified'   => ['type' => 'bool',         'default' => false,    'description' => 'Whether the user opened the comfirmation email'],
                'timezone'            => ['type' => 'varchar',      'length' => 50,        'description' => 'timezone'],
                'phone_number'        => ['type' => 'phone_number', 'description' => 'phone number'],
                'sms_carrier'         => ['type' => 'int',          'foreign key' => true, 'target' => 'SmsCarrier.id', 'multiplicity' => 'one to one', 'description' => 'foreign key to sms_carrier'],
                'sms_email'           => ['type' => 'varchar',      'length' => 191,       'description' => 'built from sms and carrier (see sms_carrier)'],
                'uri'                 => ['type' => 'varchar',      'length' => 191,       'description' => 'universally unique identifier, usually a tag URI'],
                'auto_subscribe_back' => ['type' => 'bool',         'default' => false,    'description' => 'automatically subscribe to users who subscribed us'],
                'subscription_policy' => ['type' => 'int',          'size' => 'tiny',      'default' => 0, 'description' => '0 = anybody can subscribe; 1 = require approval'],
                'is_stream_private'   => ['type' => 'bool',         'default' => false,    'description' => 'whether to limit all notices to subscribers only'],
                'created'             => ['type' => 'datetime',     'not null' => true,    'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was created'],
                'modified'            => ['type' => 'timestamp',    'not null' => true,    'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'],
            ],
            'primary key' => ['id'],
            'unique keys' => [
                'user_nickname_key'       => ['nickname'],
                'user_outgoing_email_key' => ['outgoing_email'],
                'user_incoming_email_key' => ['incoming_email'],
                'user_phone_number_key'   => ['phone_number'],
                'user_uri_key'            => ['uri'],
            ],
            'indexes' => [
                'user_nickname_idx'  => ['nickname'],
                'user_created_idx'   => ['created'],
                'user_sms_email_idx' => ['sms_email'],
            ],
        ];
    }
}
