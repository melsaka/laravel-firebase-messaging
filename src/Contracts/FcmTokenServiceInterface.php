<?php

namespace Melsaka\LaravelFirebaseMessaging\Contracts;

use Illuminate\Support\Collection;

interface FcmTokenServiceInterface
{
    public function deleteByToken(string $token): bool;
    public function deleteTokens(array $tokens): bool;
    public function getTokensForUser(int $userId): Collection;
    public function getAllTokens(): Collection;
}