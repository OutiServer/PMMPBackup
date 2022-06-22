<?php

declare(strict_types=1);

namespace Ken_Cir\Backup\Utils;

class BackupUtil
{
    /**
     * ディレクトリ内のファイルやフォルダを再帰的に取得する
     *
     * @param string $dir
     * @return string[]
     */
    public static function getFiles(string $dir): array
    {
        $files = glob(rtrim($dir, '/') . '/*');
        $list = array();
        foreach ($files as $file) {
            if (is_file($file)) {
                $list[] = $file;
            }
            if (is_dir($file)) {
                // バックアップフォルダは除外
                if (str_ends_with($file, "backup")) continue;
                $list = array_merge($list, self::getFiles($file));
            }
        }

        return $list;
    }
}