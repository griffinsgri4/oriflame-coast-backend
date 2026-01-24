<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    private function uploadsDisk(): string
    {
        return (string) (config('filesystems.uploads_disk') ?: 'public');
    }

    private function isAllowedPath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if (strpos($path, '..') !== false || strpos($path, '\\') !== false) {
            return false;
        }

        $allowedPrefixes = ['branding/', 'categories/', 'products/'];
        foreach ($allowedPrefixes as $prefix) {
            if (strpos($path, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    public function serve(Request $request, string $path)
    {
        $decoded = ltrim(rawurldecode($path), '/');
        if (!$this->isAllowedPath($decoded)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid media path',
            ], 422);
        }

        $disk = $this->uploadsDisk();
        /** @var FilesystemAdapter $fs */
        $fs = Storage::disk($disk);

        if (!$fs->exists($decoded)) {
            return response()->json([
                'status' => false,
                'message' => 'File not found',
            ], 404);
        }

        $mime = $fs->mimeType($decoded) ?: 'application/octet-stream';
        $cache = $request->query('cache', '1');
        $cacheHeader = $cache === '0'
            ? 'no-store'
            : 'public, max-age=31536000, immutable';

        $driver = (string) (config('filesystems.disks.' . $disk . '.driver') ?: '');
        if ($driver === 'local') {
            $fullPath = $fs->path($decoded);
            return response()->file($fullPath, [
                'Content-Type' => $mime,
                'Cache-Control' => $cacheHeader,
            ]);
        }

        if (method_exists($fs, 'temporaryUrl')) {
            try {
                $tmp = $fs->temporaryUrl($decoded, now()->addMinutes(10));
                return redirect()->away($tmp);
            } catch (\Throwable) {
            }
        }

        if (method_exists($fs, 'url')) {
            try {
                $url = $fs->url($decoded);
                if (is_string($url) && preg_match('#^https?://#i', $url)) {
                    return redirect()->away($url);
                }
            } catch (\Throwable) {
            }
        }

        $stream = $fs->readStream($decoded);
        if (!is_resource($stream)) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to read file',
            ], 500);
        }

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => $cacheHeader,
        ]);
    }
}
