<?php

declare(strict_types=1);

namespace outiserver\backup\tasks;

use outiserver\backup\Backup;
use pocketmine\scheduler\AsyncTask;
use ZipArchive;

/**
 * ZIP式バックアップ
 */
class ZipBackupAsyncTask extends AsyncTask
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
        $zip = new ZipArchive();
        $this->backupPath = "{$this->backupFolderPath}backups/" . date("Y-m-d-H-i-s") . ".backup.zip";
        if ($zip->open($this->backupPath, ZipArchive::CREATE) === true) {
            $this->zipSub($zip, $this->pmmpPath);
            if (!@$zip->close()) {
                $this->setResult(false);
            } else {
                $this->setResult(true);
            }
        } else {
            $this->setResult(false);
        }
    }

    public function onCompletion(): void
    {
        // 失敗、大体の原因はファイル書き込み中とか、権限がないとか
        if (!$this->getResult()) {
            Backup::getInstance()->getLogger()->error("バックアップの作成に失敗しました");
        } else {
            Backup::getInstance()->getLogger()->info("バックアップ(ZIP)を作成しました、作成先: $this->backupPath");
        }
    }

    private function zipSub(ZipArchive $zip, string $path, string $parentPath = '')
    {
        $dir = opendir($path);
        while (($entry = readdir($dir)) !== false) {
            // ここでbackupを除外しないと疑似無限ループになるので
            if ($entry == '.' || $entry == '..' || str_ends_with($entry, "backup") || str_ends_with($entry, "backup.zip")) continue;
            else {
                $localPath = "$parentPath$entry";
                $fullpath = "$path/$entry";
                if (is_file($fullpath)) {
                    $zip->addFile($fullpath, "$localPath");
                } elseif (is_dir($fullpath)) {
                    $this->zipSub($zip, $fullpath, $localPath . '/');
                }
            }
        }
        closedir($dir);
    }
}