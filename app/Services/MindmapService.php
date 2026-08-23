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
        return Mindmap::with(['user', 'logs.user'])->find($id);
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
        $newData = $data['data'] ?? [];
        $newTitle = $data['title'] ?? '未命名心智圖';
        $newFolder = $data['folder'] ?? '網站';

        if ($id) {
            $mindmap = Mindmap::findOrFail($id);
            $oldData = $mindmap->data;
            
            // Compare and log changes
            $changes = $this->diffMindmap($oldData, $newData);
            if (!empty($changes)) {
                $summary = $user->name . " 變更了 " . count($changes) . " 處結構";
                
                if ($mindmap->title !== $newTitle) {
                    $changes[] = "將標題從「{$mindmap->title}」修改為「{$newTitle}」";
                }
                if ($mindmap->folder !== $newFolder) {
                    $changes[] = "將資料夾從「{$mindmap->folder}」修改為「{$newFolder}」";
                }
                
                $mindmap->logs()->create([
                    'user_id' => $user->id,
                    'action_summary' => $summary,
                    'details' => $changes
                ]);
            } else if ($mindmap->title !== $newTitle || $mindmap->folder !== $newFolder) {
                $changes = [];
                if ($mindmap->title !== $newTitle) {
                    $changes[] = "將標題從「{$mindmap->title}」修改為「{$newTitle}」";
                }
                if ($mindmap->folder !== $newFolder) {
                    $changes[] = "將資料夾從「{$mindmap->folder}」修改為「{$newFolder}」";
                }
                $mindmap->logs()->create([
                    'user_id' => $user->id,
                    'action_summary' => $user->name . " 變更了標題/資料夾偏好",
                    'details' => $changes
                ]);
            }

            $mindmap->update([
                'title' => $newTitle,
                'folder' => $newFolder,
                'data' => $newData,
                'ai_history' => $data['ai_history'] ?? null,
            ]);
            return $mindmap;
        }

        $mindmap = $user->mindmaps()->create([
            'title' => $newTitle,
            'folder' => $newFolder,
            'data' => $newData,
            'ai_history' => $data['ai_history'] ?? null,
        ]);

        // Log the creation
        $mindmap->logs()->create([
            'user_id' => $user->id,
            'action_summary' => $user->name . " 創建了此設計藍圖",
            'details' => ["創建新專案，核心節點主題為：「{$newTitle}」"]
        ]);

        return $mindmap;
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

    /**
     * Compute differences between old and new mindmap JSON structures.
     */
    protected function diffMindmap($oldData, $newData): array
    {
        if (empty($oldData) || empty($newData)) {
            return [];
        }

        $oldFlat = [];
        $this->flattenTree($oldData, $oldFlat);

        $newFlat = [];
        $this->flattenTree($newData, $newFlat);

        $changes = [];

        // Added and modified
        foreach ($newFlat as $id => $node) {
            if (!isset($oldFlat[$id])) {
                $changes[] = "新增節點「{$node['text']}」";
            } else {
                $oldNode = $oldFlat[$id];
                $nodeChanges = [];
                if ($node['text'] !== $oldNode['text']) {
                    $nodeChanges[] = "內容從「{$oldNode['text']}」改為「{$node['text']}」";
                }
                if ($node['color'] !== $oldNode['color']) {
                    $nodeChanges[] = "顏色從「{$oldNode['color']}」改為「{$node['color']}」";
                }
                if (!empty($nodeChanges)) {
                    $changes[] = "修改節點「{$node['text']}」: " . implode(', ', $nodeChanges);
                }
            }
        }

        // Deleted
        foreach ($oldFlat as $id => $node) {
            if (!isset($newFlat[$id])) {
                $changes[] = "刪除節點「{$node['text']}」";
            }
        }

        return $changes;
    }

    /**
     * Flattens the node tree recursively.
     */
    protected function flattenTree($node, &$result)
    {
        if (empty($node)) return;
        
        $id = $node['id'] ?? null;
        if ($id) {
            $result[$id] = [
                'text' => $node['text'] ?? '',
                'color' => $node['color'] ?? '',
            ];
        }

        if (!empty($node['children'])) {
            foreach ($node['children'] as $child) {
                $this->flattenTree($child, $result);
            }
        }
    }
}
