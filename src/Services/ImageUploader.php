<?php
declare(strict_types=1);

namespace PIZ\Services;

final class ImageUploader
{
    private const MAX_BYTES = 6 * 1024 * 1024;
    private const MAX_DIMENSION = 1400;

    /**
     * Сохраняет загруженное фото товара, перекодируя его в JPEG и ограничивая размер.
     * Возвращает путь относительно корня проекта (uploads/products/xxx.jpg).
     */
    public static function save(?array $file, string $subdir = 'products'): ?string
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Не удалось загрузить фото (код ошибки ' . (int) $file['error'] . ').');
        }
        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new \RuntimeException('Некорректная загрузка файла.');
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new \RuntimeException('Фото слишком большое. Максимум 6 МБ.');
        }

        $info = @getimagesize((string) $file['tmp_name']);
        if ($info === false) {
            throw new \RuntimeException('Файл не является изображением.');
        }
        $type = $info[2] ?? 0;
        $extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        if (!isset($extensions[$type])) {
            throw new \RuntimeException('Поддерживаются изображения JPG, PNG и WEBP.');
        }

        $relativeDir = 'uploads/' . trim($subdir, '/');
        $absoluteDir = PIZ_ROOT . '/' . $relativeDir;
        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new \RuntimeException('Папка uploads недоступна для записи.');
        }

        $name = bin2hex(random_bytes(10)) . '.jpg';

        if (function_exists('imagecreatetruecolor')) {
            $contents = file_get_contents((string) $file['tmp_name']);
            $image = $contents !== false ? @imagecreatefromstring($contents) : false;
            if ($image === false) {
                throw new \RuntimeException('Не удалось обработать изображение.');
            }
            $width = imagesx($image);
            $height = imagesy($image);
            if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
                $scale = self::MAX_DIMENSION / max($width, $height);
                $newWidth = max(1, (int) round($width * $scale));
                $newHeight = max(1, (int) round($height * $scale));
                $resized = imagecreatetruecolor($newWidth, $newHeight);
                $white = imagecolorallocate($resized, 255, 255, 255);
                imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $white);
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($image);
                $image = $resized;
            } else {
                $canvas = imagecreatetruecolor($width, $height);
                $white = imagecolorallocate($canvas, 255, 255, 255);
                imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
                imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);
                imagedestroy($image);
                $image = $canvas;
            }
            $saved = imagejpeg($image, $absoluteDir . '/' . $name, 85);
            imagedestroy($image);
            if (!$saved) {
                throw new \RuntimeException('Не удалось сохранить изображение.');
            }
        } else {
            $name = bin2hex(random_bytes(10)) . '.' . $extensions[$type];
            if (!move_uploaded_file((string) $file['tmp_name'], $absoluteDir . '/' . $name)) {
                throw new \RuntimeException('Не удалось сохранить изображение.');
            }
        }

        return $relativeDir . '/' . $name;
    }

    public static function delete(?string $relativePath): void
    {
        if (!$relativePath) {
            return;
        }
        $relativePath = str_replace('\\', '/', $relativePath);
        if (!str_starts_with($relativePath, 'uploads/') || str_contains($relativePath, '..')) {
            return;
        }
        $absolute = PIZ_ROOT . '/' . $relativePath;
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    public static function url(?string $relativePath): ?string
    {
        if (!$relativePath || str_contains($relativePath, '..')) {
            return null;
        }
        $absolute = PIZ_ROOT . '/' . ltrim($relativePath, '/');
        if (!is_file($absolute)) {
            return null;
        }
        return url(ltrim($relativePath, '/')) . '?v=' . filemtime($absolute);
    }
}
