<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Resources;

use FieldVn\Zalo\Core\Http\Response;

final class UserResource extends Resource
{
    public function profile(string $userId): Response
    {
        return $this->request->get('/v3.0/oa/user/detail', [
            'data' => json_encode(['user_id' => $userId], JSON_THROW_ON_ERROR),
        ])->throwIfFailed();
    }

    /** Danh sách người quan tâm OA. */
    public function followers(int $offset = 0, int $count = 50): Response
    {
        return $this->request->get('/v3.0/oa/user/getlist', [
            'data' => json_encode([
                'offset' => $offset,
                'count' => min($count, 50),
            ], JSON_THROW_ON_ERROR),
        ])->throwIfFailed();
    }
}
