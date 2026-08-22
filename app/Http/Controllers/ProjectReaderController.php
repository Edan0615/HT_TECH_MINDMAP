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

    /**
     * Dynamic base path getter based on username request parameter.
     */
    protected function getBasePath(Request $request)
    {
        $username = $request->input('username', 'edan898');
        
        // Sanitize username to prevent path traversal
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $username)) {
            $username = 'edan898';
        }
        
        return "/home/{$username}/project";
    }

    /**
     * List all users who have project folders.
     */
    public function listUsers()
    {
        $users = [];
        if (File::exists('/home')) {
            $homeDirs = File::directories('/home');
            foreach ($homeDirs as $dir) {
                $user = basename($dir);
                // Check if directory has a project subfolder
                if (File::exists("/home/{$user}/project")) {
                    $users[] = $user;
                }
            }
        }
        
        // Make sure known users are present if their folders exist
        if (!in_array('edan898', $users)) {
            $users[] = 'edan898';
        }
        if (!in_array('shudgai999', $users) && File::exists('/home/shudgai999/project')) {
            $users[] = 'shudgai999';
        }
        if (!in_array('shanti0205', $users) && File::exists('/home/shanti0205/project')) {
            $users[] = 'shanti0205';
        }

        sort($users);

        return response()->json([
            'success' => true,
            'users' => $users
        ]);
    }

    /**
     * List all projects/folders under the base path.
     */
    public function listProjects(Request $request)
    {
        $basePath = $this->getBasePath($request);
        if (!File::exists($basePath)) {
            return response()->json(['success' => false, 'message' => "找不到專案基礎目錄 ({$basePath})。提示：若要讀取其他使用者的專案，請確保您在終端機執行過 chmod g+rx /home/使用者名稱"], 404);
        }

        try {
            $directories = File::directories($basePath);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => "讀取目錄失敗 (權限不足)。請確保該使用者的家目錄具備群組可讀權限。"], 403);
        }

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

        $basePath = $this->getBasePath($request);
        $project = $request->input('project');
        $projectPath = $basePath . '/' . $project;

        // Secure path traversal check
        $realPath = realpath($projectPath);
        if (!$realPath || !str_starts_with($realPath, $basePath)) {
            return response()->json(['success' => false, 'message' => '非法路徑存取'], 403);
        }

        if (!File::exists($realPath)) {
            return response()->json(['success' => false, 'message' => '專案路徑不存在'], 404);
        }

        // Scan files
        $finder = new Finder();
        try {
            $finder->files()
                ->in($realPath)
                ->exclude(['node_modules', 'vendor', '.git', 'dist', 'storage', 'bootstrap/cache'])
                ->notName(['*.png', '*.jpg', '*.jpeg', '*.gif', '*.ico', '*.png', '*.zip', '*.tar.gz', '*.exe', '*.sqlite', '*.lock']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => '讀取專案內部結構權限不足！'], 403);
        }

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

        $basePath = $this->getBasePath($request);
        $project = $request->input('project');
        $filePath = $request->input('file_path');
        
        $projectPath = $basePath . '/' . $project;
        $fullPath = $projectPath . '/' . $filePath;

        // Secure path traversal check
        $realProjectPath = realpath($projectPath);
        $realFilePath = realpath($fullPath);

        if (!$realProjectPath || !$realFilePath || !str_starts_with($realFilePath, $basePath)) {
            return response()->json(['success' => false, 'message' => '非法路徑存取'], 403);
        }

        if (!File::exists($realFilePath)) {
            return response()->json(['success' => false, 'message' => '檔案不存在'], 404);
        }

        try {
            $content = File::get($realFilePath);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => '讀取檔案內容失敗 (權限不足)'], 403);
        }

        return response()->json([
            'success' => true,
            'content' => $content
        ]);
    }
}
