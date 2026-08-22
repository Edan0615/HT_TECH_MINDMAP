<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class ProjectReaderController extends Controller
{
    /**
     * [CRITICAL SECURITY BOUNDARY]
     * THIS CONTROLLER IS DESIGNED TO BE STRICTLY READ-ONLY (SELECTIVE FILE READS).
     * WRITE OPERATIONS, FILE DELETIONS, OR TERMINAL EXECUTIONS ARE EXPLICITLY DENIED.
     * DO NOT IMPLEMENT OR EXECUTE ANY PAYLOADS OR WRITE APIs IN THIS SCOPE.
     */
    protected $basePath = '/home/edan898/project';

    /**
     * List all projects/folders under the base path.
     */
    public function listProjects()
    {
        if (!File::exists($this->basePath)) {
            return response()->json(['success' => false, 'message' => '找不到專案基礎目錄'], 404);
        }

        $directories = File::directories($this->basePath);
        $projects = collect($directories)->map(function ($dir) {
            return [
                'name' => basename($dir),
                'path' => $dir,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'projects' => $projects
        ]);
    }

    /**
     * Get file tree of a selected project.
     */
    public function getProjectTree(Request $request)
    {
        $request->validate([
            'project' => 'required|string',
        ]);

        $project = $request->input('project');
        $projectPath = $this->basePath . '/' . $project;

        // Secure path traversal check
        $realPath = realpath($projectPath);
        if (!$realPath || !str_starts_with($realPath, $this->basePath)) {
            return response()->json(['success' => false, 'message' => '非法路徑存取'], 403);
        }

        if (!File::exists($realPath)) {
            return response()->json(['success' => false, 'message' => '專案路徑不存在'], 404);
        }

        // Scan files
        $finder = new Finder();
        $finder->files()
            ->in($realPath)
            ->exclude(['node_modules', 'vendor', '.git', 'dist', 'storage', 'bootstrap/cache'])
            ->notName(['*.png', '*.jpg', '*.jpeg', '*.gif', '*.ico', '*.png', '*.zip', '*.tar.gz', '*.exe', '*.sqlite', '*.lock']);

        $files = [];
        foreach ($finder as $file) {
            $files[] = [
                'relative_path' => $file->getRelativePathname(),
                'name' => $file->getFilename(),
            ];
        }

        // Sort alphabetically
        usort($files, function ($a, $b) {
            return strcmp($a['relative_path'], $b['relative_path']);
        });

        return response()->json([
            'success' => true,
            'files' => $files
        ]);
    }

    /**
     * Read the content of a file.
     */
    public function readFile(Request $request)
    {
        $request->validate([
            'project' => 'required|string',
            'file_path' => 'required|string',
        ]);

        $project = $request->input('project');
        $filePath = $request->input('file_path');
        
        $projectPath = $this->basePath . '/' . $project;
        $fullPath = $projectPath . '/' . $filePath;

        // Secure path traversal check
        $realProjectPath = realpath($projectPath);
        $realFilePath = realpath($fullPath);

        if (!$realProjectPath || !$realFilePath || !str_starts_with($realFilePath, $this->basePath)) {
            return response()->json(['success' => false, 'message' => '非法路徑存取'], 403);
        }

        if (!File::exists($realFilePath)) {
            return response()->json(['success' => false, 'message' => '檔案不存在'], 404);
        }

        $content = File::get($realFilePath);

        return response()->json([
            'success' => true,
            'content' => $content
        ]);
    }
}
