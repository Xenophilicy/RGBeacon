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

use Xenophilicy\RGBeacon\RGBeacon;

class LoopTaskCaller extends Task{

	public function __construct(RGBeacon $plugin, int $id, array $data, int $speed){
		$this->plugin = $plugin;
		$this->id = $id;
		$this->data = $data;
		$this->speed = $speed;
	}
	
	public function onRun(int $currentTick){
		$i = 0;
		$this->plugin->internalData[$this->id]["TaskIDs"] = [];
		foreach($this->data["Colors"] as $name){
			$i++;
			$speed = $this->speed*$i;
            $meta = RGBeacon::getGlassMeta($name);
			$task = $this->plugin->getScheduler()->scheduleDelayedTask(new BeaconLoopTask($this->plugin, $this->data, $meta), $speed);
			array_push($this->plugin->internalData[$this->id]["TaskIDs"], $task->getTaskId());
		}
		$processSpeed = $this->speed*$i;
		$task = $this->plugin->getScheduler()->scheduleDelayedTask(new LoopTaskCaller($this->plugin, $this->id, $this->data, $this->data["Delay"]), $processSpeed);
		array_push($this->plugin->internalData[$this->id]["TaskIDs"], $task->getTaskId());
	}
}