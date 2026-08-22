<?php

namespace App\Services;

use App\Models\Mindmap;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class MindmapService
{
    /**
     * Get all mindmaps for a user.
     *
     * @param User $user
     * @return Collection
     */
    public function getUserMindmaps(User $user): Collection
    {
        return $user->mindmaps()->orderBy('updated_at', 'desc')->get();
    }

    /**
     * Get a specific mindmap if owned by the user.
     *
     * @param User $user
     * @param int $id
     * @return Mindmap|null
     */
    public function getUserMindmap(User $user, int $id): ?Mindmap
    {
        return $user->mindmaps()->find($id);
    }

    /**
     * Create or update a mindmap for a user.
     *
     * @param User $user
     * @param array $data
     * @param int|null $id
     * @return Mindmap
     */
    public function saveMindmap(User $user, array $data, ?int $id = null): Mindmap
    {
        if ($id) {
            $mindmap = $user->mindmaps()->findOrFail($id);
            $mindmap->update([
                'title' => $data['title'] ?? '未命名心智圖',
                'data' => $data['data'] ?? [],
                'ai_history' => $data['ai_history'] ?? null,
            ]);
            return $mindmap;
        }

        return $user->mindmaps()->create([
            'title' => $data['title'] ?? '未命名心智圖',
            'data' => $data['data'] ?? [],
            'ai_history' => $data['ai_history'] ?? null,
        ]);
    }

    /**
     * Delete a user's mindmap.
     *
     * @param User $user
     * @param int $id
     * @return bool
     */
    public function deleteMindmap(User $user, int $id): bool
    {
        $mindmap = $user->mindmaps()->findOrFail($id);
        return $mindmap->delete();
    }
}
