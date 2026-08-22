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
        return Mindmap::with('user')->orderBy('updated_at', 'desc')->get();
    }

    /**
     * Get a specific mindmap.
     *
     * @param User $user
     * @param int $id
     * @return Mindmap|null
     */
    public function getUserMindmap(User $user, int $id): ?Mindmap
    {
        return Mindmap::with('user')->find($id);
    }

    /**
     * Create or update a mindmap.
     *
     * @param User $user
     * @param array $data
     * @param int|null $id
     * @return Mindmap
     */
    public function saveMindmap(User $user, array $data, ?int $id = null): Mindmap
    {
        if ($id) {
            $mindmap = Mindmap::findOrFail($id);
            $mindmap->update([
                'title' => $data['title'] ?? '未命名心智圖',
                'folder' => $data['folder'] ?? '網站',
                'data' => $data['data'] ?? [],
                'ai_history' => $data['ai_history'] ?? null,
            ]);
            return $mindmap;
        }

        return $user->mindmaps()->create([
            'title' => $data['title'] ?? '未命名心智圖',
            'folder' => $data['folder'] ?? '網站',
            'data' => $data['data'] ?? [],
            'ai_history' => $data['ai_history'] ?? null,
        ]);
    }

    /**
     * Delete a mindmap.
     *
     * @param User $user
     * @param int $id
     * @return bool
     */
    public function deleteMindmap(User $user, int $id): bool
    {
        $mindmap = Mindmap::findOrFail($id);
        return $mindmap->delete();
    }
}
