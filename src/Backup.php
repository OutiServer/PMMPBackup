<?php

declare(strict_types=1);

namespace outiserver\backup;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;

class Backup extends PluginBase
{
    use SingletonTrait;

    public const CONFIG_VERSION = "1.0.0";

    private Config $config;

    /**
     * バックアップを再試行した回数
     *
     * @var int
     */
    private int $tryCounter;

    protected function onLoad(): void
    {
        self::setInstance($this);
    }

    protected function onEnable(): void
    {
        $this->tryCounter = 0;

        if (@file_exists("{$this->getDataFolder()}config.yml")) {
            $config = new Config("{$this->getDataFolder()}config.yml", Config::YAML);
            // データベース設定のバージョンが違う場合は
            if ($config->get("version") !== self::CONFIG_VERSION) {
                rename("{$this->getDataFolder()}config.yml", "{$this->getDataFolder()}config.yml.{$config->get("version")}");
                $this->getLogger()->warning("config.yml バージョンが違うため、上書きしました");
                $this->getLogger()->warning("前バージョンのconfig.ymlは{$this->getDataFolder()}config.yml.{$config->get("version")}にあります");
            }
        }

        if (!file_exists( "{$this->getDataFolder()}backups/")) {
            mkdir("{$this->getDataFolder()}backups/");
        }

        $this->saveResource("config.yml");
        $this->config = new Config("{$this->getDataFolder()}config.yml", Config::YAML);

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function (): void {
                $this->getServer()->getAsyncPool()->submitTask(new ZipBackupAsyncTask($this->getDataFolder(), $this->getServer()->getDataPath()));
            }),
            $this->getConfig()->get("interval", 60) * 60 * 20);

        $this->getServer()->getCommandMap()->registerAll($this->getName(), [
            new BackupCommand($this, "backup", "バックアップを作成する", []),
        ]);
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * @return int
     */
    public function getTryCounter(): int
    {
        return $this->tryCounter;
    }

    /**
     * @param int $tryCounter
     */
    public function setTryCounter(int $tryCounter): void
    {
        $this->tryCounter = $tryCounter;
    }
}