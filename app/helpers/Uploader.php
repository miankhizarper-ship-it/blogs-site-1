<?php
declare(strict_types=1);

namespace Helpers;

/**
 * Secure image uploader: extension + real-MIME whitelist, size cap,
 * random rename, optional resize/WebP conversion, no directory traversal.
 */
class Uploader
{
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    /**
     * @param array  $file      one entry of $_FILES
     * @param string $folder    subfolder inside /public/uploads (blogs|media|pages)
     * @param int    $maxBytes  default 3 MB
     * @return array{path:string, webp:?string, width:int, height:int, mime:string}
     * @throws \RuntimeException on any validation failure (safe messages only)
     */
    public static function image(array $file, string $folder = 'media', int $maxBytes = 3_145_728): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload failed. Check the file size limit and try again.');
        }
        if ($file['size'] > $maxBytes) {
            throw new \RuntimeException('Image is larger than ' . round($maxBytes / 1048576, 1) . ' MB.');
        }
        // Real MIME via finfo — never trust client contentType
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($file['tmp_name']);
        if (!isset(self::ALLOWED[$mime])) {
            throw new \RuntimeException('Only JPG, PNG, WebP and GIF images are allowed.');
        }
        // also confirm it decodes as an image
        if (@getimagesize($file['tmp_name']) === false) {
            throw new \RuntimeException('The file is not a valid image.');
        }

        $ext    = self::ALLOWED[$mime];
        $folder = preg_replace('/[^a-z0-9_-]/i', '', $folder) ?: 'media';
        $dir    = config('paths.uploads') . '/' . $folder;
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException('Cannot create upload directory.');
        }

        $name   = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest   = $dir . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('Could not store the uploaded file.');
        }
        chmod($dest, 0644);

        [$w, $h] = @getimagesize($dest) ?: [0, 0];

        // Bonus: generate a WebP copy for <picture>/lazy-load pipelines
        $webp = null;
        if (function_exists('imagewebp')) {
            $webpPath = $dir . '/' . pathinfo($name, PATHINFO_FILENAME) . '.webp';
            $img = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg($dest),
                'image/png'  => @imagecreatefrompng($dest),
                'image/gif'  => @imagecreatefromgif($dest),
                default      => @imagecreatefromwebp($dest),
            };
            if ($img) {
                if (imagewebp($img, $webpPath, 85)) {
                    $webp = "$folder/" . basename($webpPath);
                }
                imagedestroy($img);
            }
        }

        return [
            'path'   => "$folder/$name",
            'webp'   => $webp,
            'width'  => (int) $w,
            'height' => (int) $h,
            'mime'   => $mime,
        ];
    }

    /** Delete a stored upload safely (only inside uploads/, no traversal). */
    public static function delete(string $relativePath): bool
    {
        $base = realpath(config('paths.uploads'));
        $real = realpath(config('paths.uploads') . '/' . ltrim($relativePath, '/'));
        if (!$base || !$real || !str_starts_with($real, $base) || !is_file($real)) {
            return false;
        }
        $ok = unlink($real);
        $webp = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $real);
        if ($webp && is_file($webp)) {
            unlink($webp);
        }
        return $ok;
    }
}
