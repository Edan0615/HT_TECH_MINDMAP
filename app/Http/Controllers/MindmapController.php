<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\MindmapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MindmapController extends Controller
{
    protected MindmapService $mindmapService;

    public function __construct(MindmapService $mindmapService)
    {
        $this->mindmapService = $mindmapService;
    }

    /**
     * Display the dashboard list of user's mindmaps.
     */
    public function index(Request $request): Response
    {
        $mindmaps = $this->mindmapService->getUserMindmaps($request->user());

        return Inertia::render('Dashboard', [
            'mindmaps' => $mindmaps
        ]);
    }

    /**
     * Display the specific mindmap editor.
     */
    public function show(Request $request, int $id): Response
    {
        $mindmap = $this->mindmapService->getUserMindmap($request->user(), $id);

        if (!$mindmap) {
            abort(404, '找不到此心智圖！');
        }

        return Inertia::render('Mindmap', [
            'mindmap' => $mindmap
        ]);
    }

    /**
     * Create or update a mindmap.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:mindmaps,id',
            'title' => 'required|string|max:255',
            'folder' => 'nullable|string|max:100',
            'data' => 'required|array',
            'ai_history' => 'nullable|array',
        ]);

        $mindmap = $this->mindmapService->saveMindmap(
            $request->user(),
            $validated,
            $validated['id'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => '儲存成功！',
            'mindmap' => $mindmap
        ]);
    }

    /**
     * Delete a mindmap.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->mindmapService->deleteMindmap($request->user(), $id);

        return response()->json([
            'success' => true,
            'message' => '刪除成功！'
        ]);
    }
}
