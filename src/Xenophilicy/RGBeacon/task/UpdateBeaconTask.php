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

namespace Xenophilicy\RGBeacon\Task;

use pocketmine\scheduler\Task;
use pocketmine\block\{BlockFactory,Block};

use Xenophilicy\RGBeacon\RGBeacon;

class UpdateBeaconTask extends Task{

    public function __construct(RGBeacon $plugin){
        $this->plugin = $plugin;
    }
    
    public function onRun(int $currentTick){
        foreach($this->plugin->validBeacons as $id => $data){
            if($this->plugin->internalData[$id]["Activated"] === true){
                $coords = $data["Coords"];
                $level = $this->plugin->getServer()->getLevelByName($data["Level"]);
                $block = $level->getBlockAt($coords[0], $coords[1]-2, $coords[2]);
                $level->setBlock($block, BlockFactory::get(Block::IRON_BLOCK), true, true);
                $level->setBlock($block, BlockFactory::get(Block::BEACON), true, true);
            }
            
        }
    }
}