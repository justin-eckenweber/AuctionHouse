<?php

namespace AuctionHouse\commands;

use AuctionHouse\database\DataHolder;
use AuctionHouse\economy\EconomyProvider;
use AuctionHouse\utils\Settings;
use AuctionHouse\utils\Utils;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\item\Item;
use pocketmine\nbt\BigEndianNBTStream;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use AuctionHouse\AuctionHouse;
use pocketmine\utils\TextFormat;

class AHCommand extends Command implements PluginIdentifiableCommand {

	/** @var AuctionHouse */
	protected $plugin;

	/**
	 * EventListener constructor.
	 *
	 * @param AuctionHouse $plugin
	 */
	public function __construct(AuctionHouse $plugin) {
		parent::__construct("ah", "Opens AuctionHouse", "Usage: /ah [shop | sell | listings | update]", []);
		$this->plugin = $plugin;
		$this->setAliases(["auctionhouse"]);
		$this->setUsage(Utils::prefixMessage(TextFormat::RED . $this->getUsage()));
		$this->setPermissionMessage(Utils::prefixMessage(TextFormat::RED . "You do not have permission to use this command!"));
	}

	/**
	 * @param CommandSender $sender
	 * @param string $commandLabel
	 * @param string[] $args
	 *
	 * @return mixed
	 */
	public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
		if (!$sender instanceof Player) {
			$sender->sendMessage("You must execute this command in-game.");
			return false;
		}
		assert($sender instanceof Player);
		if (count($args) == 0 || $args[0] == "shop") {
			$this->plugin->sendAHMenu($sender);
			return true;
		}
		switch ($args[0]) {
			case "update":
				if(!$sender->hasPermission("auctionhouse.command.update")) {
					$sender->sendMessage($this->getPermissionMessage());
					return false;
				}
				$this->getPlugin()->getDatabase()->save();
				Settings::init($this->getPlugin()->getConfig());
				$sender->sendMessage(Utils::prefixMessage(TextFormat::GREEN . "Update completed"));
				return true;
			case "listings":
				$this->plugin->sendListings($sender);
				return true;
			case "sell":
				$item = $sender->getInventory()->getItemInHand();
				if($item == null || $item->getId() == Item::AIR) {
					$sender->sendMessage($this->plugin->getMessage($sender, "no-item"));
					return false;
				}
				if($sender->isCreative() && !Settings::getCreativeSale()) {
					$sender->sendMessage($this->plugin->getMessage($sender, "in-creative"));
					return false;
				}
				foreach (Settings::getBlacklist() as $blacklistedItem) {
					if($item->getId() == $blacklistedItem->getId() && $item->getDamage() == $blacklistedItem->getDamage()) {
						$sender->sendMessage($this->plugin->getMessage($sender, "item-blacklisted"));
						return false;
					}
				}
				if($args[1] == null || !is_numeric($args[1])) {
					$this->plugin->getMessage($sender, "invalid-price");
					return false;
				}
				if(count(DataHolder::getListingsByPlayer($sender)) >= (Settings::getMaxItems())) {
					$this->plugin->getMessage($sender, "max-listings");
					return false;
				}
				$listingPrice = Settings::getListingPrice();
				if(($this->getEconomy()->getMoney($sender) < $listingPrice) && $listingPrice != 0) {
					$this->plugin->getMessage($sender, "invalid-balance");
					return false;
				}
				if($listingPrice != 0) $this->getEconomy()->subtractMoney($sender, $listingPrice);
				$sender->getInventory()->removeItem($item);
				DataHolder::addListing($sender, (int) $args[1], (new BigEndianNBTStream())->writeCompressed($item->nbtSerialize()));
				$sender->sendMessage(str_replace(["@player", "@item", "@price", "@amount"], [$sender->getName(), $item->getName(), $this->getEconomy()->getMonetaryUnit() . $args[1], $item->getCount()], $this->getPlugin()->getMessage($sender, "item-listed", true)));
				return true;
		}
		$sender->sendMessage($this->getUsage());
		return true;
	}

	/**
	 * @return AuctionHouse
	 */
	public function getPlugin() : Plugin{
		return $this->plugin;
	}

	public function getEconomy() : EconomyProvider {
		return $this->getPlugin()->economyProvider;
	}
}
