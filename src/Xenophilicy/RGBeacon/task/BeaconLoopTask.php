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

use pocketmine\block\{Block, BlockFactory};
use pocketmine\scheduler\Task;
use Xenophilicy\RGBeacon\RGBeacon;

/**
 * Class BeaconLoopTask
 * @package Xenophilicy\RGBeacon\task
 */
class BeaconLoopTask extends Task {
    /**
     * @var int
     */
    private $meta;
    /**
     * @var RGBeacon
     */
    private $plugin;
    /**
     * @var array
     */
    private $data;
    
    
    /**
     * BeaconLoopTask constructor.
     * @param RGBeacon $plugin
     * @param array $data
     * @param int $meta
     */
    public function __construct(RGBeacon $plugin, array $data, int $meta){
        $this->plugin = $plugin;
        $this->data = $data;
        $this->meta = $meta;
    }
    
    /**
     * @param int $currentTick
     */
    public function onRun(int $currentTick){
        $level = $this->plugin->getServer()->getLevelByName($this->data["Level"]);
        $coords = $this->data["Coords"];
        if($this->plugin->colorType === "block"){
            $glassBlock = BlockFactory::get(Block::STAINED_GLASS, $this->meta);
        }else{
            $glassBlock = BlockFactory::get(Block::STAINED_GLASS_PANE, $this->meta);
        }
        $level->setBlock($level->getBlockAt($coords[0], $coords[1] - 1, $coords[2]), $glassBlock, true, true);
    }
}