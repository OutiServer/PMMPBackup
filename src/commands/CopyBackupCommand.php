<?php

declare(strict_types=1);

namespace outiserver\backup\commands;

use outiserver\backup\Backup;
use outiserver\backup\tasks\CopyBackupAsyncTask;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\Server;

class CopyBackupCommand extends Command implements PluginOwned
{
    private Plugin $plugin;

    public function __construct(Plugin $plugin, string $name, Translatable|string $description = "", Translatable|string|null $usageMessage = null, array $aliases = [])
    {
        parent::__construct($name, $description, $usageMessage, $aliases);

        $this->plugin = $plugin;
        $this->setPermission("backup.command");
    }

    public function getOwningPlugin(): Plugin
    {
        return $this->plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        Server::getInstance()->getAsyncPool()->submitTask(new CopyBackupAsyncTask(Backup::getInstance()->getDataFolder(), Server::getInstance()->getDataPath()));
    }
}