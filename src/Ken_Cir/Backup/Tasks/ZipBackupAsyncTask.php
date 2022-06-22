<?php

declare(strict_types=1);

namespace Ken_Cir\Backup\Tasks;

use Ken_Cir\Backup\Backup;
use Ken_Cir\Backup\Utils\BackupUtil;
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
        var_dump("{$this->backupFolder}backups/");
        if ($zip->open("{$this->backupFolder}backups/" . date("Y-m-d-H-i-s") . ".backup.zip", ZipArchive::CREATE) === true) {
            $files = BackupUtil::getFiles($this->pmmpPath);
            foreach ($files as $file) {
                $zip->addFile($file);
            }

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
}