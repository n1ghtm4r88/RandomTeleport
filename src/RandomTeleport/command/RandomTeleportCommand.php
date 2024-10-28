<?php

declare(strict_types=1);

namespace RandomTeleport\command;

use pocketmine\command\Command;
use RandomTeleport;
use RandomTeleport\permission\PermissionNames;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use pocketmine\permission\DefaultPermissions;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\world\format\Chunk;
use pocketmine\math\Vector3;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\sound\EndermanTeleportSound;

class RandomTeleportCommand extends Command
{
    private const BLINDNESS_DURATION = 20 * 4;
    private const TASK_DELAY = 20 / 4;

    public function __construct(private readonly RandomTeleport $plugin)
    {
        parent::__construct("rtp", "Teleport to a random location");

        $permissionName = PermissionNames::COMMAND_RANDOMTELEPORT;
        $candidate = new Permission($permissionName);
        $grantedBy = [PermissionManager::getInstance()->getPermission(DefaultPermissions::ROOT_USER)];

        DefaultPermissions::registerPermission($candidate, $grantedBy);

        $this->setPermission($permissionName);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : bool
    {
        if (!$sender instanceof Player)
        {
            $sender->sendMessage("Use only in-game");

            return true;
        }

        $world = $sender->getWorld();
        $availableWorlds = ["world"];

        if (!in_array($world->getDisplayName(), $availableWorlds))
        {
            $sender->sendMessage("Use only on available worlds");

            return true;
        }

        $effect = $sender->getEffects()->get(VanillaEffects::BLINDNESS());
        $effects = $sender->getEffects();
        $duration = $effect !== null ? $effect->getDuration() + self::BLINDNESS_DURATION : self::BLINDNESS_DURATION;

        // Recommend using in worlds without a void to prevent player fall-through
        // Or just restrict the coordinates
        $x = mt_rand(-5000, 5000);
        $z = mt_rand(-5000, 5000);

        $effects->remove(VanillaEffects::BLINDNESS());
        $effects->add(new EffectInstance(VanillaEffects::BLINDNESS(), $duration));

        $world->orderChunkPopulation($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE, null)->onCompletion(

            function () use ($world, $x, $z, $effect, $effects, $sender) : void
            {
                $highestBlock = $world->getHighestBlockAt($x, $z);

                if ($highestBlock === null)
                {
                    if ($effect !== null)
                    {
                        $effects->remove($effect->getType());
                        $effects->add($effect->setDuration(max(0, $effect->getDuration() - self::BLINDNESS_DURATION)));
                    }

                    // The world is assumed to have an overworld dimension
                    // As PM5 lacks other measurements
                    // PM5 won't let you check the world dimension

                    $sender->sendMessage("An error occurred");

                    return;
                }

                $y = $highestBlock + 1;
                $pos = new Vector3($x, $y, $z);
                $task = new ClosureTask(fn () => $world->addSound($pos, new EndermanTeleportSound()));

                $this->plugin->getScheduler()->scheduleDelayedTask($task, self::TASK_DELAY);
                $sender->teleport($pos);
                $sender->sendMessage("You teleported to $x $y $z");
            },
            fn () => null
        );

        return true;
    }
}
