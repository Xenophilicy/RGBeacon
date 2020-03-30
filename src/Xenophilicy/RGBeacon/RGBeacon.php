<?php
# MADE BY:
#  __    __                                          __        __  __  __                     
# /  |  /  |                                        /  |      /  |/  |/  |                    
# $$ |  $$ |  ______   _______    ______    ______  $$ |____  $$/ $$ |$$/   _______  __    __ 
# $$  \/$$/  /      \ /       \  /      \  /      \ $$      \ /  |$$ |/  | /       |/  |  /  |
#  $$  $$<  /$$$$$$  |$$$$$$$  |/$$$$$$  |/$$$$$$  |$$$$$$$  |$$ |$$ |$$ |/$$$$$$$/ $$ |  $$ |
#   $$$$  \ $$    $$ |$$ |  $$ |$$ |  $$ |$$ |  $$ |$$ |  $$ |$$ |$$ |$$ |$$ |      $$ |  $$ |
#  $$ /$$  |$$$$$$$$/ $$ |  $$ |$$ \__$$ |$$ |__$$ |$$ |  $$ |$$ |$$ |$$ |$$ \_____ $$ \__$$ |
# $$ |  $$ |$$       |$$ |  $$ |$$    $$/ $$    $$/ $$ |  $$ |$$ |$$ |$$ |$$       |$$    $$ |
# $$/   $$/  $$$$$$$/ $$/   $$/  $$$$$$/  $$$$$$$/  $$/   $$/ $$/ $$/ $$/  $$$$$$$/  $$$$$$$ |
#                                         $$ |                                      /  \__$$ |
#                                         $$ |                                      $$    $$/ 
#                                         $$/                                        $$$$$$/
namespace Xenophilicy\RGBeacon;

use pocketmine\plugin\PluginBase;
use pocketmine\block\{Block,BlockFactory};
use pocketmine\item\{ItemBlock,Item};
use pocketmine\command\{Command,CommandSender};
use pocketmine\utils\{Config,TextFormat as TF};
#use pocketmine\event\level\ChunkLoadEvent;
use pocketmine\event\Listener;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\Player;
use pocketmine\tile\Tile;

use Xenophilicy\RGBeacon\task\{LoopTaskCaller,UpdateBeaconTask};
use Xenophilicy\RGBeacon\block\Beacon;
use Xenophilicy\RGBeacon\inventory\BeaconInventory;
use Xenophilicy\RGBeacon\packet\InventoryTransactionPacketV2;
use Xenophilicy\RGBeacon\tile\Beacon as BeaconTile;

class RGBeacon extends PluginBase implements Listener{

    protected static $inventories = [];

    private static $colors = [
        0 => "white",
        1 => "orange",
        2 => "magenta",
        3 => "light blue",
        4 => "yellow",
        5 => "lime",
        6 => "pink",
        7 => "gray",
        8 => "light gray",
        9 => "cyan",
        10 => "purple",
        11 => "blue",
        12 => "brown",
        13 => "green",
        14 => "red",
        15 => "black"
    ];

    private static $usages = [
        "remove" => "/beacon remove <id>",
        "pause" => "/beacon pause <id>",
        "resume" => "/beacon resume <id>",
        "hide" => "/beacon hide <id>",
        "show" => "/beacon show <id>",
        "set" => "/beacon set <id> <setting> <delay-ticks|color-list>",
        "list" => "/beacon list <world|all>"
    ];

    private $levels = [];

    public function onLoad() {
        $this->saveDefaultConfig();
        $this->config = new Config($this->getDataFolder()."config.yml", Config::YAML);
        $this->config->getAll();
    }
    
    public static function getGlassMeta(string $name){
        return array_flip(self::$colors)[$name];
    }

	public function onEnable(){
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $version = $this->config->get("VERSION");
        $this->pluginVersion = $this->getDescription()->getVersion();
        if($version < "1.0.0"){
            $this->getLogger()->warning("You have updated RGBeacon to v".$this->pluginVersion." but have a config from v$version! Please delete your old config for new features to be enabled and to prevent unwanted errors! Plugin will remain disabled...");
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
        $loadLevels = [];
        foreach(scandir($this->getServer()->getDataPath()."worlds/") as $level){
            if($level === "." || $level === ".."){
                continue;
            } else{
                array_push($loadLevels, $level);
                $this->getServer()->loadLevel($level); 
            }
        }
        if(($listMode = $this->config->getNested("Worlds.Mode")) === false){
            foreach($loadLevels as $level){
                $level = $this->getServer()->getLevelByName($level);
                array_push($this->levels, $level->getName());
            }
        } else{
            $worldList = $this->config->getNested("Worlds.List");
            foreach($worldList as $world){
                if($this->getServer()->getLevelByName($world) === null){
                    $this->getLogger()->critical("Invalid world name! Name: ".$world." was not found, disabling plugin! Be sure you use the name of the world folder for the world name in the config!");
                    $this->getServer()->getPluginManager()->disablePlugin($this);
                    return;
                }
            }
            foreach($loadLevels as $level){
                $level = $this->getServer()->getLevelByName($level);
                if(($listMode === "whitelist" && in_array($level->getName(), $worldList)) || ($listMode === "blacklist" && !in_array($level->getName(), $worldList))){
                    array_push($this->levels, $level->getName());
                }
            }
        }
        $this->colorType = strtolower($this->config->get("Color-Type"));
        if($this->colorType !== "block" && $this->colorType !== "pane"){
            $this->getLogger()->critical("Invalid beacon color type, disabling plugin! Valid types are block or pane, invalid type: ".$this->colorType);
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }
        if($this->checkCreationDefaults($this->config->get("Defaults")) === false){
            $this->defaults["colors"] = ["white"];
            $this->defaults["delay"] = 80;
        }
        $this->validBeacons = [];
        $this->beaconList = [];
        $beaconsFound = $this->config->get("Beacons");
        if($beaconsFound !== []){
            if(($checkedBeacons = $this->checkBeacons($beaconsFound)) === []){
                $this->getLogger()->critical("All beacons were unable to start correctly, the plugin will remain disabled until a valid beacon is found...");
                $this->getServer()->getPluginManager()->disablePlugin($this);
                return;
            }
            $total = count($beaconsFound);
            if(is_int($result = $this->startAllBeacons($checkedBeacons))){
                if($result < count($beaconsFound)){
                    $this->getLogger()->warning($total-$result." beacon(s) did not start correctly");
                } else{
                    $this->getLogger()->info($total." beacon(s) started");
                }
                $refreshDelay = $this->config->get("Beacon-Refresh-Delay");
                if($refreshDelay !== false && $refreshDelay !== null){
                    if(is_int($refreshDelay)){
                        $this->refreshBeacons($this->config->get("Beacon-Refresh-Delay"));
                    } else{
                        $this->getLogger()->warning("Invalid beacon refresh delay found, defaulting to 300 ticks...");
                    }
                }
            } else{
                $this->getLogger()->critical("Fatal error encountered while starting beacons! Plugin disabling...");
                $this->getServer()->getPluginManager()->disablePlugin($this);
                return;
            }
        }
	}

    private static function checkColor(string $name) : bool{
        foreach(array_values(self::$colors) as $color){
            if($name === $color){
                return true;
            }
        }
        return false;
    }

    private function checkCreationDefaults($defaults) : bool{
        if(!is_array($defaults["Colors"])){
            $this->getLogger()->warning("Invalid default color array found, all plugin defaults will be used!");
                return false;
        }
        foreach(($colors = $defaults["Colors"]) as $name){
            if(!is_string($name) || !self::checkColor($name)){
                $this->getLogger()->warning("Invalid default color found, all plugin defaults will be used!");
                return false;
            }
        }
        if(!is_int($delay = $defaults["Delay"])){
            $this->getLogger()->warning("Invalid default tick delay found, all plugin defaults will be used!");
            return false;
        }
        $this->defaults["colors"] = $colors;
        $this->defaults["delay"] = $delay;
        return true;
    }

    private function checkBeacons($beacons) : array{
        $checkedBeacons = [];
        foreach($beacons as $id => $data){
            if(!is_int($id)){
                $this->getLogger()->warning("Invalid beacon ID found as ".$id.", it will be disabled!");
                continue;
            }
            if($data["Level"] === null || ($this->getServer()->getLevelByName($data["Level"])) === null){
                $level = $data["Level"] !== null ? $data["Level"] : "NULL";
                $this->getLogger()->warning("Invalid level name found on beacon ID ".$id." as ".$level.", it will be disabled!");
                continue;
            }
            $level = $this->getServer()->getLevelByName($data["Level"])->getName();
            if(!in_array($level, $this->levels)){
                $this->getLogger()->warning("Beacon ID ".$id." cannot be started on level ".$level." due to whitelist/blacklist, it will be removed from the config!");
                continue;
            }
            if(!is_array($data["Coords"]) || count($data["Coords"]) < 3){
                $this->getLogger()->warning("Invalid coordinate array found on beacon ID ".$id.", it will be disabled!");
                continue;
            }
            foreach($data["Coords"] as $coord){
                if(!is_numeric($coord)){
                    $this->getLogger()->warning("Invalid coordinate array found on beacon ID ".$id.", it will be disabled!");
                    continue 2;
                }
            }
            if(!is_int($data["Delay"])){
                $delay = $data["Delay"] !== null ? $data["Delay"] : "NULL";
                $this->getLogger()->warning("Invalid tick delay found on beacon ID ".$id." as ".$delay.", it will be disabled!");
                continue;
            }
            foreach($data["Replaced"] as $block){
                $values = explode(":",$block);
                try{
                    Block::get((int)$values[0],isset($values[1]) ? (int)$values[1]:0);
                } catch(\InvalidArgumentException $e){
                    $this->getLogger()->warning("Invalid block found on beacon ID ".$id." as ".$block.", it will be disabled!");
                    continue 2;
                }
            }
            foreach($data["Base"] as $block){
                $values = explode(":",$block);
                try{
                    Block::get((int)$values[0],isset($values[1]) ? (int)$values[1]:0);
                } catch(\InvalidArgumentException $e){
                    $this->getLogger()->warning("Invalid base block found on beacon ID ".$id." as ".$block.", it will be disabled!");
                    continue 2;
                }
            }
            if(!is_array($data["Colors"])){
                $this->getLogger()->warning("Invalid color entry array found on beacon ID ".$id.", it will be disabled!");
                continue;
            }
            foreach($data["Colors"] as $name){
                if(!is_string($name) || !self::checkColor($name)){
                    $this->getLogger()->warning("Invalid color entry found on beacon ID ".$id." as ".$name.", it will be disabled!");
                    continue 2;
                }
            }
            $checkedBeacons[$id] = $data;
        }
        return $checkedBeacons;
    }

    private function startAllBeacons($beacons) : ?int{
        $started = 0;
        try{
            foreach($beacons as $id => $data){
                $this->startBeacon($id, $data);
                $started++;
            }
            return $started;
        } catch(\Exception | \ErrorException $e){
            return null;
        }
    }

    private function startBeacon(int $id, array $data) : void{
        $coords = $data["Coords"];
        $speed = $data["Delay"];
        $level = $this->getServer()->getLevelByName($data["Level"]);
        $block = $level->getBlockAt($coords[0], $coords[1]-2, $coords[2]);
        $level->setBlock($block, BlockFactory::get(Block::BEACON), true, true);
        $pos = $block->getSide(0);
        for($blockX = $pos->x - 1; $blockX <= $pos->x + 1; $blockX++){
            for($blockZ = $pos->z - 1; $blockZ <= $pos->z + 1; $blockZ++){
                $baseBlock = $level->getBlockAt($blockX, $pos->y, $blockZ);
                $level->setBlock($baseBlock, BlockFactory::get(Block::IRON_BLOCK), true, false);
            }
        }
        $task = $this->getScheduler()->scheduleTask(new LoopTaskCaller($this, $id, $data, $speed));
        $this->internalData[$id]["Paused"] = false;
        $this->internalData[$id]["Activated"] = true;
        $this->internalData[$id]["TaskIDs"] = [$task->getTaskID()];
        $this->validBeacons[$id] = $data;
        $this->beaconList[$id] = $data;
        $this->config->set("Beacons", $this->beaconList);
        $this->config->save();
        return;
    }

    private function createBeacon(Player $player) : void{
        $x = $player->getX();
        $y = $player->getY();
        $z = $player->getZ();
        $level = $player->getLevel();
        if(!in_array($level->getName(), $this->levels)){
            $player->sendMessage(TF::RED."Beacons cannot be created on level ".$level->getName());
            return;
        }
        foreach($this->validBeacons as $id => $data){
            if($level->getName() == $data["Level"]){
                $coords = $data["Coords"];
                $dBlock = $level->getBlockAt($x, $y, $z);
                $eBlock = $level->getBlockAt($coords[0], $coords[1], $coords[2]);
                if($dBlock->x == $eBlock->x && $dBlock->y == $eBlock->y && $dBlock->z == $eBlock->z){
                    $player->sendMessage(TF::RED."Beacon (ID ".$id.") already exists at (".(int)$x.", ".(int)$y.", ".(int)$z.")");
                    return;
                }
            }
        }
        $tBlock = $level->getBlockAt($x, $y-1, $z);
        $bBlock = $level->getBlockAt($x, $y-2, $z);
        $id = sizeof($this->validBeacons) === 0 ? 1 : max(array_keys($this->validBeacons))+1;
        while(array_key_exists($id, $this->validBeacons)){
            $id++;
        }
        $pos = $bBlock->getSide(0);
        $baseBlocks = [];
        for($blockX = $pos->x - 1; $blockX <= $pos->x + 1; $blockX++){
            for($blockZ = $pos->z - 1; $blockZ <= $pos->z + 1; $blockZ++){
                $baseBlock = $level->getBlockAt($blockX, $pos->y, $blockZ);
                $level->setBlock($baseBlock, BlockFactory::get(Block::IRON_BLOCK), true, false);
                array_push($baseBlocks, $baseBlock->getId().":".$baseBlock->getDamage());
            }
        }
        $data = array(
            "Level" => $level->getName(),
            "Coords" => [$x, $y, $z],
            "Delay" => $this->defaults["delay"],
            "Replaced" => array(
                "Glass" => $tBlock->getId().":".$tBlock->getDamage(),
                "Beacon" => $bBlock->getId().":".$bBlock->getDamage()
            ),
            "Base" => $baseBlocks,
            "Colors" => $this->defaults["colors"]
        );
        if($this->colorType === "block"){
            $level->setBlock($tBlock, BlockFactory::get(Block::STAINED_GLASS), true, true);
        } else{
            $level->setBlock($tBlock, BlockFactory::get(Block::STAINED_GLASS_PANE), true, true);
        }
        $this->startBeacon($id, $data);
        $player->sendMessage(TF::GREEN."Beacon".TF::BLUE." (ID ".$id.") ".TF::GREEN."created at ".TF::YELLOW."(".(int)$x.", ".(int)$y.", ".(int)$z.")");
        return;
    }

    private function removeBeacon(CommandSender $sender, int $id) : void{
        if(in_array($id, array_keys($this->beaconList))){
            if(in_array($id, array_keys($this->validBeacons))){
                foreach($this->internalData[$id]["TaskIDs"] as $taskID){
                    $this->internalData[$id]["TaskIDs"] = [];
                    $this->getScheduler()->cancelTask($taskID);
                }
                $data = $this->validBeacons[$id];
                unset($this->validBeacons[$id]);
                unset($this->beaconList[$id]);
                $level = $this->getServer()->getLevelByName($data["Level"]);
                $coords = $data["Coords"];
                $tBlock = $level->getBlockAt($coords[0],$coords[1]-1,$coords[2]);
                $bBlock = $level->getBlockAt($coords[0],$coords[1]-2,$coords[2]);
                $values = explode(":",$data["Replaced"]["Glass"]);
                $level->setBlock($tBlock, BlockFactory::get((int)$values[0],isset($values[1]) ? (int)$values[1]:0));
                $values = explode(":",$data["Replaced"]["Beacon"]);
                $level->setBlock($bBlock, BlockFactory::get((int)$values[0],isset($values[1]) ? (int)$values[1]:0));
                $pos = $bBlock->getSide(0);
                $i = 0;
                for($blockX = $pos->x - 1; $blockX <= $pos->x + 1; $blockX++){
                    for($blockZ = $pos->z - 1; $blockZ <= $pos->z + 1; $blockZ++){
                        $replacement = $data["Base"][$i];
                        $i++;
                        $baseBlock = $level->getBlockAt($blockX, $pos->y, $blockZ);
                        $values = explode(":",$replacement);
                        $level->setBlock($baseBlock, Block::get((int)$values[0],isset($values[1]) ?(int)$values[1]:0));
                    }
                }
                $this->config->removeNested("Beacons.".$id);
                $this->config->save();
                $sender->sendMessage(TF::GREEN."Beacon".TF::BLUE." (ID ".$id.") ".TF::GREEN."removed successfully");
                return;
            } else{
                $sender->sendMessage(TF::RED."Beacon".TF::BLUE." (ID ".$id.") ".TF::RED."is invalid and cannot be removed");
            }
        } else{
            $sender->sendMessage(TF::RED."The beacon specified doesn't exist");
            return;
        }
    }

    private function pauseBeacon(CommandSender $sender, int $id) : void{
        if(in_array($id, array_keys($this->beaconList))){
            if(in_array($id, array_keys($this->validBeacons))){
                if($this->internalData[$id]["Paused"] === false){
                    $this->internalData[$id]["Paused"] = true;
                    foreach($this->internalData[$id]["TaskIDs"] as $taskID){
                        $this->internalData[$id]["TaskIDs"] = [];
                        $this->getScheduler()->cancelTask($taskID);
                    }
                    $sender->sendMessage(TF::GREEN."Beacon".TF::BLUE." (ID ".$id.") ".TF::GREEN."paused successfully");
                } else{
                    $sender->sendMessage(TF::YELLOW."Beacon".TF::BLUE." (ID ".$id.") ".TF::YELLOW."is already paused");
                }
            } else{
                $sender->sendMessage(TF::RED."Beacon".TF::BLUE." (ID ".$id.") ".TF::RED."exists but is not enabled");
            }
        } else{
            $sender->sendMessage(TF::RED."The beacon specified doesn't exist");
        }
        return;
    }

    private function resumeBeacon(CommandSender $sender, int $id) : void{
        if(in_array($id, array_keys($this->beaconList))){
            if(in_array($id, array_keys($this->validBeacons))){
                if($this->internalData[$id]["Paused"] === true){
                    $this->internalData[$id]["Paused"] = false;
                    if($this->internalData[$id]["Activated"] === false){
                        $sender->sendMessage(TF::YELLOW."Beacon".TF::BLUE." (ID ".$id.") ".TF::YELLOW." was re-activated because it was deactivatd before resuming");
                    }
                    $this->startBeacon($id, $this->validBeacons[$id]);
                    $sender->sendMessage(TF::GREEN."Beacon".TF::BLUE." (ID ".$id.") ".TF::GREEN."resumed successfully");
                } else{
                    $sender->sendMessage(TF::YELLOW."Beacon".TF::BLUE." (ID ".$id.") ".TF::YELLOW."is not currently paused");
                }
            } else{
                $sender->sendMessage(TF::RED."Beacon".TF::BLUE." (ID ".$id.") ".TF::RED."exists but is not enabled");
            }
        } else{
            $sender->sendMessage(TF::RED."The beacon specified doesn't exist");
        }
        return;
    }

    public function hideBeacon(CommandSender $sender = null, int $id) : void{
        if(in_array($id, array_keys($this->beaconList))){
            if(in_array($id, array_keys($this->validBeacons))){
                if($this->internalData[$id]["Activated"] === true){
                    $this->internalData[$id]["Activated"] = false;
                    $data = $this->validBeacons[$id];
                    $coords = $data["Coords"];
                    $level = $this->getServer()->getLevelByName($data["Level"]);
                    $block = $level->getBlockAt($coords[0], $coords[1]-2, $coords[2]);
                    $level->setBlock($block, BlockFactory::get(Block::IRON_BLOCK), true, true);
                    if($sender === null){
                        return;
                    }
                    $sender->sendMessage(TF::GREEN."Beacon".TF::BLUE." (ID ".$id.") ".TF::GREEN."deactivated successfully");
                } elseif($sender !== null){
                    $sender->sendMessage(TF::YELLOW."Beacon".TF::BLUE." (ID ".$id.") ".TF::YELLOW."is already deactivated");
                }
            } elseif($sender !== null){
                $sender->sendMessage(TF::RED."Beacon".TF::BLUE." (ID ".$id.") ".TF::RED."exists but is not enabled");
            }
        } elseif($sender !== null){
            $sender->sendMessage(TF::RED."The beacon specified doesn't exist");
        }
        return;
    }

    public function showBeacon(CommandSender $sender = null, int $id) : void{
        if(in_array($id, array_keys($this->beaconList))){
            if(in_array($id, array_keys($this->validBeacons))){
                if($this->internalData[$id]["Activated"] === false){
                    $this->internalData[$id]["Activated"] = true;
                    $data = $this->validBeacons[$id];
                    $coords = $data["Coords"];
                    $level = $this->getServer()->getLevelByName($data["Level"]);
                    $level->loadChunk($coords[0], $coords[2]);
                    $block = $level->getBlockAt($coords[0], $coords[1]-2, $coords[2]);
                    $level->setBlock($block, BlockFactory::get(Block::BEACON), true, true);
                    if($sender === null){
                        return;
                    }
                    $sender->sendMessage(TF::GREEN."Beacon".TF::BLUE." (ID ".$id.") ".TF::GREEN."activated successfully");
                } elseif($sender !== null){
                    $sender->sendMessage(TF::YELLOW."Beacon".TF::BLUE." (ID ".$id.") ".TF::YELLOW."is already activated");
                }
            } elseif($sender !== null){
                $sender->sendMessage(TF::RED."Beacon".TF::BLUE." (ID ".$id.") ".TF::RED."exists but is not enabled");
            }
        } elseif($sender !== null){
            $sender->sendMessage(TF::RED."The beacon specified doesn't exist");
        }
        return;
    }

    protected function setBeaconConfig(CommandSender $sender, array $args) : void{
        $id = $args[1];
        $type = $args[2];
        $setting = $args[3];
        if(in_array($id, array_keys($this->beaconList))){
            switch($type){
                case "speed":
                case "delay":
                    if($this->internalData[$id]["Paused"] === true){
                        $sender->sendMessage(TF::YELLOW."Beacon".TF::BLUE." (ID ".$id.") ".TF::YELLOW." is currently paused. Resume it with ".TF::GREEN."/beacon resume $id");
                        break;
                    }
                    if($this->internalData[$id]["Activated"] === false){
                        $sender->sendMessage(TF::YELLOW."Beacon".TF::BLUE." (ID ".$id.") ".TF::YELLOW." is currently deactivated. Activate it with ".TF::GREEN."/beacon show $id");
                        break;
                    }
                    if(ctype_digit($setting)){
                        $this->config->setNested("Beacons.".$id.".Delay", intval($setting));
                        $this->config->save();
                    $this->reloadBeacons();
                        $sender->sendMessage(TF::GREEN."Beacon".TF::BLUE." (ID ".$id.") ".TF::GREEN."delay successfully updated to ".TF::LIGHT_PURPLE.$setting);
                    } else{
                        $sender->sendMessage(TF::RED."Beacon delay setting must be an integer");
                    }
                    break;
                case "colors":
                    if($this->internalData[$id]["Paused"] === true){
                        $sender->sendMessage(TF::YELLOW."Beacon".TF::BLUE." (ID ".$id.") ".TF::YELLOW." is currently paused. Resume it with ".TF::GREEN."/beacon resume $id");
                        break;
                    }
                    if($this->internalData[$id]["Activated"] === false){
                        $sender->sendMessage(TF::YELLOW."Beacon".TF::BLUE." (ID ".$id.") ".TF::YELLOW." is currently deactivated. Activate it with ".TF::GREEN."/beacon show $id");
                        break;
                    }
                    $colors = [];
                    array_push($colors, $setting);
                    str_replace(" ", "", $setting);
                    if(strpos($setting, ",") !== false){
                        $colors = [];
                        if(count($args) > 4){
                            $sender->sendMessage(TF::RED."Beacon colors must be separated by commas");
                            return;
                        }
                        $values = explode(",", $setting);
                        foreach($values as $name){
                            if(!is_string($name) || !self::checkColor($name)){
                                $sender->sendMessage(TF::RED.$name." is not a valid beacon color");
                                return;
                            }
                            array_push($colors, $name);
                        }
                    } elseif(!is_string($setting) || !self::checkColor($setting)){
                        $sender->sendMessage(TF::RED.$setting." is not a valid beacon color");
                        return;
                    }
                    $this->config->setNested("Beacons.".$id.".Colors", $colors);
                    $this->config->save();
                    $this->reloadBeacons();
                    if(is_array($colors)){
                        $colors = (implode(", ", $colors));
                    }
                    $sender->sendMessage(TF::GREEN."Beacon".TF::BLUE." (ID ".$id.") ".TF::GREEN."colors successfully updated to ".TF::LIGHT_PURPLE.$colors);
                    break;
                default:
                    $this->sendUsage($sender, "set");
            }
        } else{
            $sender->sendMessage(TF::RED."The beacon specified doesn't exist");
        }
        return;
    }

    private function reloadBeacons(CommandSender $sender = null) : void{
        $this->config->save();
        $this->config = new Config($this->getDataFolder()."config.yml", Config::YAML);
        $this->config->getAll();
        foreach(array_keys($this->validBeacons) as $id){
            foreach($this->internalData[$id]["TaskIDs"] as $taskID){
                $this->internalData[$id]["TaskIDs"] = [];
                $this->getScheduler()->cancelTask($taskID);
            }
        }
        $this->validBeacons = [];
        $this->beaconList = $this->config->get("Beacons");
        $checkedBeacons = $this->checkBeacons($this->beaconList);
        $this->startAllBeacons($checkedBeacons);
        if($sender !== null){
            $sender->sendMessage(TF::GREEN."All beacons have been reloaded, reactivated, and resumed");
        }
        return;
    }
    
    private function listBeacons(CommandSender $sender, string $mode) : void{
        if($mode === "all" || !($sender instanceof Player)){
            if($this->validBeacons === [] || count($this->validBeacons) < 1){
                if($sender instanceof Player){
                    $sender->sendMessage(TF::YELLOW."There are no beacons on the server yet, create one with ".TF::GREEN."/beacon new");
                    return;
                } else{
                    $sender->sendMessage(TF::YELLOW."There are no beacons on the server to list");
                    return;
                }
            }
            $sender->sendMessage(TF::GRAY."---".TF::GOLD." All Beacons ".TF::GRAY."---");
            foreach($this->validBeacons as $id => $data){
                $coords = $data["Coords"];
                $sender->sendMessage(TF::GREEN."Beacon ".TF::BLUE.$id.TF::GREEN." @ ".TF::YELLOW."(".(int)$coords[0].", ".(int)$coords[1].", ".(int)$coords[2].")".TF::GREEN." on ".TF::LIGHT_PURPLE.$data["Level"]);
            }
            $sender->sendMessage(TF::GRAY."-------------------");
        } elseif($mode === "world"){
            $level = $sender->getLevel()->getName();
            $beaconsFound = 0;
            foreach($this->validBeacons as $id => $data){
                if($data["Level"] === $level){
                    $beaconsFound++;
                }
            }
            if($beaconsFound < 1){
                $sender->sendMessage(TF::YELLOW."There are no beacons on level ".$level." yet, create one with ".TF::GREEN."/beacon new");
                return;
            }
            $sender->sendMessage(TF::GRAY."--- ".TF::GOLD.$level." Beacons ".TF::GRAY."---");
            foreach($this->validBeacons as $id => $data){
                if($data["Level"] === $level){
                    $coords = $data["Coords"];
                    $sender->sendMessage(TF::GREEN."Beacon ".TF::BLUE.$id.TF::GREEN." @ ".TF::YELLOW."(".(int)$coords[0].", ".(int)$coords[1].", ".(int)$coords[2].")");
                    $beaconsFound++;
                }
            }
            $sender->sendMessage(TF::GRAY."-------------------");
        } else{
            $this->sendUsage($sender, "list");
        }
        return;
    }

    private function sendUsage(CommandSender $sender, string $subcommand) : void{
        $sender->sendMessage(TF::RED."Usage: ".self::$usages[$subcommand]);
    }

    private function sendHelp(CommandSender $sender) : void{
        $sender->sendMessage(TF::GRAY."---".TF::GOLD." RGBeacon Commands ".TF::GRAY."---");
        $sender->sendMessage(TF::YELLOW."/beacon help [colors] → ".TF::BLUE."View help page [view available colors]");
        $sender->sendMessage(TF::YELLOW."/beacon new → ".TF::BLUE."Creates a new beacon under the player");
        $sender->sendMessage(TF::YELLOW."/beacon remove <id> → ".TF::BLUE."Removes a selected beacon from the world");
        $sender->sendMessage(TF::YELLOW."/beacon pause <id> → ".TF::BLUE."Pause a beacon's color cycle");
        $sender->sendMessage(TF::YELLOW."/beacon resume <id> → ".TF::BLUE."Resume a beacon's color cycle if it is currently paused");
        $sender->sendMessage(TF::YELLOW."/beacon hide <id> → ".TF::BLUE."Temporarily disables a beacon's beam");
        $sender->sendMessage(TF::YELLOW."/beacon show <id> → ".TF::BLUE."Enables a beacon's beam if it's currently hidden");
        $sender->sendMessage(TF::YELLOW."/beacon set <id> <setting> <ticks|colors> → ".TF::BLUE."Set the delay or color order of a beacon");
        $sender->sendMessage(TF::YELLOW."/beacon reload → ".TF::BLUE."Reload the plugin's config and reset all beacons");
        $sender->sendMessage(TF::YELLOW."/beacon list <world|all> → ".TF::BLUE."List all beacons or just ones in the current world");
        $sender->sendMessage(TF::GRAY."-------------------");
        return;
    }

    public function sendColorHelp(CommandSender $sender) : void{
        $colorString = implode(", ", self::$colors);
        $sender->sendMessage(TF::BLUE."Beacon colors: ".TF::GREEN.$colorString);
        return;
    }

    # This is currently a (shitty) hack to get beacons to show when a chunk unloads then loads again

    private function refreshBeacons(int $delay) : void{
        $this->getScheduler()->scheduleRepeatingTask(new UpdateBeaconTask($this, $this->validBeacons), $delay);
    }

    /*
    public function onChunkLoad(ChunkLoadEvent $event) : void{
        $eChunk = $event->getChunk();
        $eLevel = $event->getLevel();
        foreach($this->validBeacons as $id => $data){
            $bLevel = $this->getServer()->getLevelByName($data["Level"]);
            if($eLevel->getName() === $bLevel->getName()){
                $coords = $data["Coords"];
                $block = $bLevel->getBlockAt($coords[0], $coords[1], $coords[2]);
                if($block->getX() >> 4 == $eChunk->getX() && $block->getZ() >> 4 == $eChunk->getZ()){
                    $this->getScheduler()->scheduleDelayedTask(new UpdateBeaconTask($this, $id, $data), 200);
                }
            }
        }
        return;
    }
    */

    private function hasPerms(CommandSender $sender) : bool{
        if($sender->isOp()){
            return true;
        }
        $perms = $sender->getEffectivePermissions();
        foreach($perms as $perm){
            $permString = $perm->getPermission();
            if(substr($permString, 0, 16) === "rgbeacon.command"){
                return true;
            }
        }
        return false;
    }

    private function noPermission(CommandSender $sender) : void{
        $sender->sendMessage(TF::RED."You do not have permission to use this command");
        return;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if($command->getName() == "rgbeacon"){
            $sender->sendMessage(TF::GRAY."---".TF::GOLD." RGBeacon ".TF::GRAY."---");
            $sender->sendMessage(TF::YELLOW."Version: ".TF::AQUA.$this->pluginVersion);
            $sender->sendMessage(TF::YELLOW."Description: ".TF::AQUA."Spawn multicolored beacons in your worlds");
            $sender->sendMessage(TF::GREEN."Command: ".TF::BLUE."/beacon help");
            $sender->sendMessage(TF::GRAY."-------------------");
        }
        elseif($command->getName() == "beacon"){
            if($this->hasPerms($sender)){
                if(isset($args[0])){
                    switch($args[0]){
                        case "spawn":
                        case "create":
                        case "new":
                            if($sender instanceof Player){
                                if($sender->hasPermission("rgbeacon.command.new")){
                                    $this->createBeacon($sender);
                                } else{
                                    $this->noPermission($sender);
                                }
                            } else{
                                $sender->sendMessage(TF::RED."Beacons can only be created in-game");
                            }
                            break;
                        case "del":
                        case "rem":
                        case "delete":
                        case "remove":
                            if($sender->hasPermission("rgbeacon.command.remove")){
                                if(isset($args[1])){
                                    $this->removeBeacon($sender, $args[1]);
                                }else{
                                    $this->sendUsage($sender, "remove");
                                }
                            } else{
                                $this->noPermission($sender);
                            }
                            break;
                        case "stop":
                        case "pause":
                            if($sender->hasPermission("rgbeacon.command.pause")){
                                if(isset($args[1])){
                                    $this->pauseBeacon($sender, $args[1]);
                                }else{
                                    $this->sendUsage($sender, "pause");
                                }
                            } else{
                                $this->noPermission($sender);
                            }
                            break;
                        case "start":
                        case "play":
                        case "resume":
                            if($sender->hasPermission("rgbeacon.command.resume")){
                                if(isset($args[1])){
                                    $this->resumeBeacon($sender, $args[1]);
                                }else{
                                    $this->sendUsage($sender, "resume");
                                }
                            } else{
                                $this->noPermission($sender);
                            }
                            break;
                        case "deactivate":
                        case "disable":
                        case "hide":
                            if($sender->hasPermission("rgbeacon.command.hide")){
                                if(isset($args[1])){
                                    $this->hideBeacon($sender, $args[1]);
                                }else{
                                    $this->sendUsage($sender, "hide");
                                }
                            } else{
                                $this->noPermission($sender);
                            }
                            break;
                        case "activate":
                        case "enable":
                        case "show":
                            if($sender->hasPermission("rgbeacon.command.show")){
                                if(isset($args[1])){
                                    $this->showBeacon($sender, $args[1]);
                                }else{
                                    $this->sendUsage($sender, "show");
                                }
                            } else{
                                $this->noPermission($sender);
                            }
                            break;
                        case "configure":
                        case "change":
                        case "edit":
                        case "set":
                            if($sender->hasPermission("rgbeacon.command.set")){
                                if(isset($args[1]) && isset($args[2]) && isset($args[3])){
                                    $this->setBeaconConfig($sender, $args);
                                }else{
                                    $this->sendUsage($sender, "set");
                                }
                            } else{
                                $this->noPermission($sender);
                            }
                            break;
                        case "reload":
                            if($sender->hasPermission("rgbeacon.command.reload")){
                                $this->reloadBeacons($sender);
                            } else{
                                $this->noPermission($sender);
                            }
                            break;
                        case "list":
                            if($sender->hasPermission("rgbeacon.command.list")){
                                if(isset($args[1])){
                                    $this->listBeacons($sender, $args[1]);
                                }else{
                                    $this->sendUsage($sender, "list");
                                }
                            } else{
                                $this->noPermission($sender);
                            }
                            break;
                        case "help":
                            if(isset($args[1])){
                                if($args[1] === "colors"){
                                    $this->sendColorHelp($sender);
                                    break;
                                }
                            }
                        default:
                            $this->sendHelp($sender);
                    }
                } else{
                    $this->sendHelp($sender);
                }
            } else{
                $this->noPermission($sender);
            }
        }
        return true;
    }
}