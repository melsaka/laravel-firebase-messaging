<?php

namespace Melsaka\LaravelFirebaseMessaging\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Melsaka\LaravelFirebaseMessaging\Contracts\FcmTokenServiceInterface;

class FcmTokenService implements FcmTokenServiceInterface
{
    protected string $table;

    public function __construct()
    {
        $this->table = config('firebase-messaging.tokens_table', 'fcm_tokens');
    }

    public function deleteByToken(string $token): bool
    {
        return DB::table($this->table)
            ->where('fcm_token', $token)
            ->delete() > 0;
    }

    public function deleteTokens(array $tokens): bool
    {
        if (empty($tokens)) {
            return true;
        }

        return DB::table($this->table)
            ->whereIn('fcm_token', $tokens)
            ->delete() > 0;
    }

    public function getTokensForUser(int $userId): Collection
    {
        return DB::table($this->table)
            ->where('user_id', $userId)
            ->get();
    }

    public function getAllTokens(): Collection
    {
        return DB::table($this->table)->get();
    }
}