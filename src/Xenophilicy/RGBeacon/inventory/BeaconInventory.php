<?php

# CREDIT:
# This is not mine, as I am using code from jasonwynn10's repository: PM-Beacons
# Repo on GitHub can be found here: https://github.com/jasonwynn10/PM-Beacons
# The plugin allows beacons to be used in Pocketmine

declare(strict_types=1);
namespace Xenophilicy\RGBeacon\inventory;

use pocketmine\inventory\ContainerInventory;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\types\WindowTypes;
use pocketmine\Player;

class BeaconInventory extends ContainerInventory {

	/**
	 * BeaconInventory constructor.
	 *
	 * @param Position $pos
	 */
	public function __construct(Position $pos) {
		parent::__construct($pos->asPosition());
	}

	/**
	 * @return int
	 */
	public function getNetworkType() : int {
		return WindowTypes::BEACON;
	}

	/**
	 * @return string
	 */
	public function getName() : string {
		return "Beacon";
	}

	/**
	 * @return int
	 */
	public function getDefaultSize() : int {
		return 1;
	}

	/**
	 * @return Position
	 */
	public function getHolder() {
		/** @noinspection PhpIncompatibleReturnTypeInspection */
		return parent::getHolder();
	}

	/**
	 * @param Player $who
	 */
	public function onClose(Player $who) : void {
		parent::onClose($who);
		$this->dropContents($who->getLevel(), $this->holder->add(0.5, 0.5, 0.5));
	}
}