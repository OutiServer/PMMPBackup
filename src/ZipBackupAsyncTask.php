<?php

declare(strict_types=1);

namespace outiserver\backup;

use pocketmine\scheduler\AsyncTask;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
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
    private string $backupFolder;

    /**
     * PocketMine-MPのフォルダパス
     * @var string
     */
    private string $pmmpPath;

    public function __construct(string $backupFolder, string $pmmpPath)
    {
        $this->backupFolder = $backupFolder;
        $this->pmmpPath = $pmmpPath;

        Backup::getInstance()->getLogger()->info("バックアップを作成しています...");
    }

    public function onRun(): void
    {
        $zip = new ZipArchive();
        if ($zip->open("{$this->backupFolder}backups/" . date("Y-m-d-H-i-s") . ".backup.zip", ZipArchive::CREATE) === true) {
            var_dump($this->pmmpPath);
            $this->zipSub($zip, $this->pmmpPath);
            if (!@$zip->close()) {
                $this->setResult(false);
            }
            else {
                $this->setResult(true);
            }
        }
        else {
            $this->setResult(false);
        }
    }

    public function onCompletion(): void
    {
        // 失敗、大体の原因はファイル書き込み中とか、権限がないとか
        if (!$this->getResult()) {
            // try
            if (Backup::getInstance()->getConfig()->get("try_count", 0) > 0) {
                // 再試行回数の上限に達した
                if (($tryCount = Backup::getInstance()->getTryCounter()) >= Backup::getInstance()->getConfig()->get("try_count", 0)) {

                    Backup::getInstance()->getLogger()->error("バックアップの作成に失敗しました、{$tryCount}回再試行しました");
                    Backup::getInstance()->getLogger()->warning("再試行回数の上限に達したためバックアップ再試行を終了しました");
                    Backup::getInstance()->setTryCounter(0);
                }
                // まだ
                else {
                    $timeout = Backup::getInstance()->getConfig()->get("try_timeout", 60);
                    Backup::getInstance()->getLogger()->error("バックアップの作成に失敗しました、{$tryCount}回再試行しました");
                    Backup::getInstance()->getLogger()->info("{$timeout}秒後に再試行を試みます");
                    Backup::getInstance()->setTryCounter($tryCount + 1);

                    Backup::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function (): void {
                        Server::getInstance()->getAsyncPool()->submitTask(new self($this->backupFolder, $this->pmmpPath));
                    }), $timeout * 20);
                }
            }
            // Oops.
            else {
                Backup::getInstance()->getLogger()->error("バックアップの作成に失敗しました");
                Backup::getInstance()->setTryCounter(0);
            }
        }
        else {
            Backup::getInstance()->getLogger()->info("バックアップを作成しました");
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