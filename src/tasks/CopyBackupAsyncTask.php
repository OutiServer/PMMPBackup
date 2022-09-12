<?php

declare(strict_types=1);

namespace outiserver\backup\tasks;

use outiserver\backup\Backup;
use pocketmine\scheduler\AsyncTask;

class CopyBackupAsyncTask extends AsyncTask
{
    /**
     * バックアップ先のフォルダ
     *
     * @var string
     */
    private string $backupFolderPath;

    /**
     * PocketMine-MPのフォルダパス
     * @var string
     */
    private string $pmmpPath;

    /**
     * バックアップ先のパス
     * @var string
     */
    private string $backupPath;

    public function __construct(string $backupFolderPath, string $pmmpPath)
    {
        $this->backupFolderPath = $backupFolderPath;
        $this->pmmpPath = $pmmpPath;

        Backup::getInstance()->getLogger()->info("バックアップを作成しています...");
    }

    public function onRun(): void
    {
        $this->backupPath = "{$this->backupFolderPath}backups/" . date("Y-m-d-H-i-s") . ".backup";
        mkdir($this->backupPath);
        $this->copy($this->pmmpPath, $this->backupPath);
    }

    public function onCompletion(): void
    {
        Backup::getInstance()->getLogger()->info("バックアップ(コピー)は正常に作成されたはずです");
        Backup::getInstance()->getLogger()->info("コピー先: $this->backupPath");
    }

    private function copy($dir, $new_dir)
    {
        $dir = rtrim($dir, '/') . '/';
        $new_dir = rtrim($new_dir, '/') . '/';

        if (is_dir($dir)) {
            if (!is_dir($new_dir)) {
                mkdir($new_dir);
                chmod($new_dir, 0777);
            }

            if ($handle = opendir($dir)) {
                while (false !== ($file = readdir($handle))) {
                    if ($file === '.' || $file === '..' || str_ends_with($file, "backup") || str_ends_with($file, "backup.zip")) {
                        continue;
                    }
                    if (is_dir($dir . $file)) {
                        $this->copy($dir . $file, $new_dir . $file);
                    } else {
                        copy($dir . $file, $new_dir . $file);
                    }
                }
                closedir($handle);
            }
        }
    }
}