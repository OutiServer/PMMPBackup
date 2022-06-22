<?php

declare(strict_types=1);

namespace Ken_Cir\Backup\Commands;

use CortexPE\Commando\BaseCommand;
use Ken_Cir\Backup\Backup;
use Ken_Cir\Backup\Tasks\ZipBackupAsyncTask;
use pocketmine\command\CommandSender;
use pocketmine\Server;

class BackupCommand extends BaseCommand
{
    protected function prepare(): void
    {
        $this->setPermission("backup.command");
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $sender->sendMessage("バックアップを開始しています...");
        Server::getInstance()->getAsyncPool()->submitTask(new ZipBackupAsyncTask(Backup::getInstance()->getDataFolder(), Server::getInstance()->getFilePath()));
    }
}