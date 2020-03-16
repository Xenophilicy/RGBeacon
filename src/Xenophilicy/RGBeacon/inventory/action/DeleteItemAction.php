<?php

# CREDIT:
# This is not mine, as I am using code from jasonwynn10's repository: PM-Beacons
# Repo on GitHub can be found here: https://github.com/jasonwynn10/PM-Beacons
# The plugin allows beacons to be used in Pocketmine

declare(strict_types=1);
namespace Xenophilicy\RGBeacon\inventory\action;

use pocketmine\inventory\transaction\action\CreativeInventoryAction;
use pocketmine\item\Item;
use pocketmine\Player;

class DeleteItemAction extends CreativeInventoryAction {

	public function __construct(Item $sourceItem, Item $targetItem){
		parent::__construct($sourceItem, $targetItem, CreativeInventoryAction::TYPE_DELETE_ITEM);
	}

	public function isValid(Player $source) : bool {
		return true;
	}
}