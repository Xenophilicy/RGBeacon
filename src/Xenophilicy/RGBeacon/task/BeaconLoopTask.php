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

namespace Xenophilicy\RGBeacon\task;

use pocketmine\block\{BlockFactory,Block};
use pocketmine\scheduler\Task;

use Xenophilicy\RGBeacon\RGBeacon;

class BeaconLoopTask extends Task{

    public function __construct(RGBeacon $plugin, array $data, int $meta){
        $this->plugin = $plugin;
        $this->data = $data;
        $this->meta = $meta;
    }
    
    public function onRun(int $currentTick){
        $level = $this->plugin->getServer()->getLevelByName($this->data["Level"]);
        $coords = $this->data["Coords"];
        if($this->plugin->colorType === "block"){
            $glassBlock = BlockFactory::get(Block::STAINED_GLASS, $this->meta);
        } else{
            $glassBlock = BlockFactory::get(Block::STAINED_GLASS_PANE, $this->meta);
        }
        $level->setBlock($level->getBlockAt($coords[0],$coords[1]-1,$coords[2]), $glassBlock, true, true);
    }
}