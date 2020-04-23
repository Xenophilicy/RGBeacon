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

use pocketmine\scheduler\Task;
use Xenophilicy\RGBeacon\RGBeacon;

/**
 * Class LoopTaskCaller
 * @package Xenophilicy\RGBeacon\task
 */
class LoopTaskCaller extends Task {
    /**
     * @var int
     */
    private $id;
    /**
     * @var RGBeacon
     */
    private $plugin;
    /**
     * @var array
     */
    private $data;
    /**
     * @var int
     */
    private $speed;
    
    
    /**
     * LoopTaskCaller constructor.
     * @param RGBeacon $plugin
     * @param int $id
     * @param array $data
     * @param int $speed
     */
    public function __construct(RGBeacon $plugin, int $id, array $data, int $speed){
        $this->plugin = $plugin;
        $this->id = $id;
        $this->data = $data;
        $this->speed = $speed;
    }
    
    /**
     * @param int $currentTick
     */
    public function onRun(int $currentTick){
        $i = 0;
        $this->plugin->internalData[$this->id]["TaskIDs"] = [];
        foreach($this->data["Colors"] as $name){
            $i++;
            $speed = $this->speed * $i;
            $meta = RGBeacon::getGlassMeta($name);
            $task = $this->plugin->getScheduler()->scheduleDelayedTask(new BeaconLoopTask($this->plugin, $this->data, $meta), $speed);
            array_push($this->plugin->internalData[$this->id]["TaskIDs"], $task->getTaskId());
        }
        $processSpeed = $this->speed * $i;
        $task = $this->plugin->getScheduler()->scheduleDelayedTask(new LoopTaskCaller($this->plugin, $this->id, $this->data, $this->data["Delay"]), $processSpeed);
        array_push($this->plugin->internalData[$this->id]["TaskIDs"], $task->getTaskId());
    }
}