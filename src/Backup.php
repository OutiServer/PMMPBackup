<?php

declare(strict_types=1);

namespace outiserver\backup;

use outiserver\backup\commands\CopyBackupCommand;
use outiserver\backup\commands\ZipBackupCommand;
use outiserver\backup\tasks\CopyBackupAsyncTask;
use outiserver\backup\tasks\ZipBackupAsyncTask;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;

class Backup extends PluginBase
{
    use SingletonTrait;

    public const CONFIG_VERSION = "1.0.0";

    private Config $config;

    protected function onLoad(): void
    {
        self::setInstance($this);
    }

    protected function onEnable(): void
    {
        if (@file_exists("{$this->getDataFolder()}config.yml")) {
            $config = new Config("{$this->getDataFolder()}config.yml", Config::YAML);
            if ($config->get("version") !== self::CONFIG_VERSION) {
                rename("{$this->getDataFolder()}config.yml", "{$this->getDataFolder()}config.yml.{$config->get("version")}");
                $this->getLogger()->warning("config.yml バージョンが違うため、上書きしました");
                $this->getLogger()->warning("前バージョンのconfig.ymlは{$this->getDataFolder()}config.yml.{$config->get("version")}にあります");
            }
        }

        if (!file_exists("{$this->getDataFolder()}backups/")) {
            mkdir("{$this->getDataFolder()}backups/");
        }

        $this->saveResource("config.yml");
        $this->config = new Config("{$this->getDataFolder()}config.yml", Config::YAML);

        if ($this->config->get("mode") !== "zip" and $this->config->get("mode") !== "copy") {
            $this->config->set("mode", "copy");
            $this->config->save();
            $this->getLogger()->warning("Configのmodeの設定値が不正だったため、上書きしました");
        }

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function (): void {
                switch ($this->config->get("mode")) {
                    case "zip":
                        $this->getServer()->getAsyncPool()->submitTask(new ZipBackupAsyncTask($this->getDataFolder(), $this->getServer()->getDataPath()));
                        break;
                    case "copy":
                        $this->getServer()->getAsyncPool()->submitTask(new CopyBackupAsyncTask($this->getDataFolder(), $this->getServer()->getDataPath()));
                        break;
                }
            }),
            $this->getConfig()->get("interval", 60) * 60 * 20);

        $this->getServer()->getCommandMap()->registerAll($this->getName(), [
            new ZipBackupCommand($this, "zipbackup", "ZIPバックアップを作成する", "/zipbackup", []),
            new CopyBackupCommand($this, "copybackup", "コピーバックアップを作成する", "/copybackup", [])
        ]);
    }

    public function getConfig(): Config
    {
        return $this->config;
    }
}