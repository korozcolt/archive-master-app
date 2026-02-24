<?php

namespace App\Support;

use BladeUI\Icons\Exceptions\SvgNotFound;
use BladeUI\Icons\Factory;

class FileExtensionIcon
{
    /**
     * @return array{icon:string,bg:string,fg:string,badge:string,label:string}
     */
    public static function meta(?string $extension): array
    {
        $ext = self::normalizeExtension($extension);

        return match ($ext) {
            'pdf' => [
                'icon' => self::resolveIcon('tni-pdf-o', 'heroicon-o-document-text'),
                'bg' => 'bg-rose-100 dark:bg-rose-900/30',
                'fg' => 'text-rose-600 dark:text-rose-300',
                'badge' => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/40 dark:bg-rose-900/20 dark:text-rose-300',
                'label' => 'PDF',
            ],
            'doc', 'docx', 'odt', 'rtf', 'pages' => [
                'icon' => self::resolveIcon('tni-doc-o', 'heroicon-o-document-text'),
                'bg' => 'bg-sky-100 dark:bg-sky-900/30',
                'fg' => 'text-sky-600 dark:text-sky-300',
                'badge' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/40 dark:bg-sky-900/20 dark:text-sky-300',
                'label' => strtoupper($ext),
            ],
            'xls', 'xlsx', 'csv', 'ods', 'numbers' => [
                'icon' => self::resolveIcon(
                    in_array($ext, ['csv'], true) ? 'tni-csv-o' : 'tni-xls-o',
                    'heroicon-o-table-cells'
                ),
                'bg' => 'bg-emerald-100 dark:bg-emerald-900/30',
                'fg' => 'text-emerald-600 dark:text-emerald-300',
                'badge' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-300',
                'label' => strtoupper($ext),
            ],
            'ppt', 'pptx', 'odp', 'key' => [
                'icon' => self::resolveIcon('tni-ppt-o', 'heroicon-o-presentation-chart-bar'),
                'bg' => 'bg-amber-100 dark:bg-amber-900/30',
                'fg' => 'text-amber-600 dark:text-amber-300',
                'badge' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/40 dark:bg-amber-900/20 dark:text-amber-300',
                'label' => strtoupper($ext),
            ],
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'tif', 'tiff', 'heic', 'heif', 'avif' => [
                'icon' => self::resolveIcon(
                    match ($ext) {
                        'png' => 'tni-png-o',
                        'jpg', 'jpeg' => 'tni-jpg-o',
                        default => 'tni-img-o',
                    },
                    'heroicon-o-photo'
                ),
                'bg' => 'bg-fuchsia-100 dark:bg-fuchsia-900/30',
                'fg' => 'text-fuchsia-600 dark:text-fuchsia-300',
                'badge' => 'border-fuchsia-200 bg-fuchsia-50 text-fuchsia-700 dark:border-fuchsia-900/40 dark:bg-fuchsia-900/20 dark:text-fuchsia-300',
                'label' => strtoupper($ext),
            ],
            'zip', 'rar', '7z', 'tar', 'gz', 'tgz', 'bz2', 'xz' => [
                'icon' => self::resolveIcon('tni-zip-o', 'heroicon-o-archive-box'),
                'bg' => 'bg-violet-100 dark:bg-violet-900/30',
                'fg' => 'text-violet-600 dark:text-violet-300',
                'badge' => 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900/40 dark:bg-violet-900/20 dark:text-violet-300',
                'label' => strtoupper($ext),
            ],
            'txt', 'md', 'log', 'ini', 'env', 'conf' => [
                'icon' => self::resolveIcon('tni-txt-o', 'heroicon-o-document'),
                'bg' => 'bg-slate-100 dark:bg-slate-800',
                'fg' => 'text-slate-600 dark:text-slate-300',
                'badge' => 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200',
                'label' => strtoupper($ext),
            ],
            'json', 'xml', 'yml', 'yaml', 'sql', 'js', 'ts', 'jsx', 'tsx', 'php', 'py', 'java', 'kt', 'go', 'rs', 'c', 'cpp', 'h', 'hpp', 'css', 'scss', 'sass', 'html', 'htm', 'vue', 'blade.php' => [
                'icon' => self::resolveIcon('tni-code-o', 'heroicon-o-code-bracket'),
                'bg' => 'bg-cyan-100 dark:bg-cyan-900/30',
                'fg' => 'text-cyan-600 dark:text-cyan-300',
                'badge' => 'border-cyan-200 bg-cyan-50 text-cyan-700 dark:border-cyan-900/40 dark:bg-cyan-900/20 dark:text-cyan-300',
                'label' => strtoupper($ext),
            ],
            'mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac' => [
                'icon' => self::resolveIcon('tni-music-o', 'heroicon-o-musical-note'),
                'bg' => 'bg-pink-100 dark:bg-pink-900/30',
                'fg' => 'text-pink-600 dark:text-pink-300',
                'badge' => 'border-pink-200 bg-pink-50 text-pink-700 dark:border-pink-900/40 dark:bg-pink-900/20 dark:text-pink-300',
                'label' => strtoupper($ext),
            ],
            'mp4', 'mov', 'avi', 'mkv', 'webm', 'wmv', 'm4v' => [
                'icon' => self::resolveIcon('tni-mp4-o', 'heroicon-o-film'),
                'bg' => 'bg-indigo-100 dark:bg-indigo-900/30',
                'fg' => 'text-indigo-600 dark:text-indigo-300',
                'badge' => 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-900/40 dark:bg-indigo-900/20 dark:text-indigo-300',
                'label' => strtoupper($ext),
            ],
            'dwg', 'dxf', 'stp', 'step', 'igs', 'iges' => [
                'icon' => 'heroicon-o-cube',
                'bg' => 'bg-orange-100 dark:bg-orange-900/30',
                'fg' => 'text-orange-600 dark:text-orange-300',
                'badge' => 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900/40 dark:bg-orange-900/20 dark:text-orange-300',
                'label' => strtoupper($ext),
            ],
            'ai', 'psd', 'fig', 'sketch', 'xd' => [
                'icon' => self::resolveIcon('tni-img-o', 'heroicon-o-swatch'),
                'bg' => 'bg-purple-100 dark:bg-purple-900/30',
                'fg' => 'text-purple-600 dark:text-purple-300',
                'badge' => 'border-purple-200 bg-purple-50 text-purple-700 dark:border-purple-900/40 dark:bg-purple-900/20 dark:text-purple-300',
                'label' => strtoupper($ext),
            ],
            default => [
                'icon' => self::resolveIcon('tni-file-o', 'heroicon-o-document'),
                'bg' => 'bg-slate-100 dark:bg-slate-800',
                'fg' => 'text-slate-600 dark:text-slate-300',
                'badge' => 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200',
                'label' => $ext === '' ? 'ARCHIVO' : strtoupper($ext),
            ],
        };
    }

    public static function extensionFromPath(?string $path): string
    {
        if (! is_string($path) || $path === '') {
            return '';
        }

        $filename = pathinfo($path, PATHINFO_BASENAME);
        $lowerFilename = strtolower($filename);

        if (str_ends_with($lowerFilename, '.blade.php')) {
            return 'blade.php';
        }

        return self::normalizeExtension(pathinfo($path, PATHINFO_EXTENSION));
    }

    public static function normalizeExtension(?string $extension): string
    {
        if (! is_string($extension)) {
            return '';
        }

        return strtolower(trim(ltrim($extension, '.')));
    }

    private static function resolveIcon(string ...$candidates): string
    {
        $fallback = 'heroicon-o-document';

        /** @var Factory|null $factory */
        $factory = app()->bound(Factory::class) ? app(Factory::class) : null;

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            if ($factory === null) {
                return $candidate;
            }

            try {
                $factory->svg($candidate);

                return $candidate;
            } catch (SvgNotFound) {
                continue;
            }
        }

        return $fallback;
    }
}
