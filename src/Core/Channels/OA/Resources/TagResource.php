<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Resources;

use FieldVn\Zalo\Core\Http\Response;

final class TagResource extends Resource
{
    public function all(): Response
    {
        return $this->request->get('/v3.0/oa/tag/gettagsofoa')->throwIfFailed();
    }

    public function assign(string $userId, string $tagName): Response
    {
        return $this->request->post('/v3.0/oa/tag/tagfollower', [
            'user_id' => $userId,
            'tag_name' => $tagName,
        ])->throwIfFailed();
    }

    public function remove(string $userId, string $tagName): Response
    {
        return $this->request->post('/v3.0/oa/tag/rmfollowerfromtag', [
            'user_id' => $userId,
            'tag_name' => $tagName,
        ])->throwIfFailed();
    }
}
