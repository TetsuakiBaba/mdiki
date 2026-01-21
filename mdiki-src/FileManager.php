<?php

namespace Mdiki;

class FileManager
{
    private $mdikiRoot;
    private $maxUploadSize; // in bytes

    public function __construct($mdikiRoot, $maxUploadSizeMB = 10)
    {
        $this->mdikiRoot = realpath($mdikiRoot);
        $this->maxUploadSize = $maxUploadSizeMB * 1024 * 1024;
    }

    public function getFullPath($relativePath)
    {
        $relativePath = Utils::sanitizePath($relativePath);
        $fullPath = realpath($this->mdikiRoot . '/' . $relativePath);

        if ($fullPath === false || strpos($fullPath, $this->mdikiRoot) !== 0) {
            // If file doesn't exist yet, we check the parent directory
            $parentDir = realpath(dirname($this->mdikiRoot . '/' . $relativePath));
            if ($parentDir === false || strpos($parentDir, $this->mdikiRoot) !== 0) {
                throw new \Exception("Invalid path: $relativePath");
            }
            return $this->mdikiRoot . '/' . $relativePath;
        }

        return $fullPath;
    }

    public function listFiles($dir = '')
    {
        $fullPath = $this->getFullPath($dir);
        $items = [];
        $files = scandir($fullPath);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $filePath = $fullPath . '/' . $file;
            $relativePath = ltrim(str_replace($this->mdikiRoot, '', $filePath), '/');
            $isDir = is_dir($filePath);
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);

            if ($isDir || $ext === 'md' || $isImage) {
                $items[] = [
                    'name' => $file,
                    'path' => $relativePath,
                    'is_dir' => $isDir,
                    'mtime' => filemtime($filePath),
                    'size' => $isDir ? 0 : filesize($filePath),
                    'children' => $isDir ? $this->listFiles($relativePath) : []
                ];
            }
        }
        return $items;
    }

    public function readFile($path)
    {
        $fullPath = $this->getFullPath($path);
        if (!is_file($fullPath)) {
            throw new \Exception("File not found");
        }
        return file_get_contents($fullPath);
    }

    public function saveFile($path, $content)
    {
        $fullPath = $this->getFullPath($path);
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($fullPath, $content);
    }

    public function deleteFile($path)
    {
        $fullPath = $this->getFullPath($path);
        if (is_dir($fullPath)) {
            // Logic for recursive delete or move to trash
            // For now, simple delete
            $this->rrmdir($fullPath);
        } else {
            unlink($fullPath);
        }
    }

    public function createDirectory($path)
    {
        $fullPath = $this->mdikiRoot . '/' . Utils::sanitizePath($path);
        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }
    }

    public function move($oldPath, $newPath)
    {
        $oldFullPath = $this->getFullPath($oldPath);
        $newFullPath = $this->mdikiRoot . '/' . Utils::sanitizePath($newPath);

        $newDir = dirname($newFullPath);
        if (!is_dir($newDir)) {
            mkdir($newDir, 0755, true);
        }

        return rename($oldFullPath, $newFullPath);
    }

    public function uploadImage($file, $targetDir = '')
    {
        $targetDir = Utils::sanitizePath($targetDir);
        $fullTargetDir = $this->mdikiRoot . ($targetDir ? '/' . $targetDir : '');

        if (!is_dir($fullTargetDir)) {
            mkdir($fullTargetDir, 0755, true);
        }

        $fileName = basename($file['name']);
        $targetPath = $fullTargetDir . '/' . $fileName;

        // Check file size
        if ($file['size'] > $this->maxUploadSize) {
            $limitMB = $this->maxUploadSize / (1024 * 1024);
            throw new \Exception("File is too large. Maximum size allowed is {$limitMB}MB.");
        }

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($file['tmp_name']);
        if (!in_array($fileType, $allowedTypes)) {
            throw new \Exception("Invalid file type: $fileType");
        }

        // Check for existing file and append suffix if needed
        $info = pathinfo($fileName);
        $name = $info['filename'];
        $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
        $counter = 1;
        while (file_exists($targetPath)) {
            $fileName = $name . '_' . $counter . $ext;
            $targetPath = $fullTargetDir . '/' . $fileName;
            $counter++;
        }

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ltrim(str_replace($this->mdikiRoot, '', $targetPath), '/');
        }

        throw new \Exception("Failed to move uploaded file.");
    }

    private function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object))
                        $this->rrmdir($dir . DIRECTORY_SEPARATOR . $object);
                    else
                        unlink($dir . DIRECTORY_SEPARATOR . $object);
                }
            }
            rmdir($dir);
        }
    }
}
