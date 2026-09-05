<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Resources;

use FieldVn\Zalo\Core\Http\Response;

/**
 * Nhãn người quan tâm OA.
 *
 * Tag API vẫn ở v2.0. Gọi /v3.0/oa/tag/* thì Zalo trả empty or invalid API.
 */
final class TagResource extends Resource
{
    public function all(): Response
    {
        return $this->request->get('/v2.0/oa/tag/gettagsofoa')->throwIfFailed();
    }

    public function assign(string $userId, string $tagName): Response
    {
        return $this->request->post('/v2.0/oa/tag/tagfollower', [
            'user_id' => $userId,
            'tag_name' => $tagName,
        ])->throwIfFailed();
    }

    public function remove(string $userId, string $tagName): Response
    {
        return $this->request->post('/v2.0/oa/tag/rmfollowerfromtag', [
            'user_id' => $userId,
            'tag_name' => $tagName,
        ])->throwIfFailed();
    }
}
