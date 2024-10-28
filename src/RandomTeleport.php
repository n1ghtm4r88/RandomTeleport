<?php

declare(strict_types=1);

use pocketmine\plugin\PluginBase;
use RandomTeleport\command\RandomTeleportCommand;

class RandomTeleport extends PluginBase
{
    public function onEnable() : void
    {
        $command = new RandomTeleportCommand($this);

        $this->getServer()->getCommandMap()->register($this->getName(), $command);
    }
}
