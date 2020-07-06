<?php

declare(strict_types=1);

namespace muqsit\chestshop;

use muqsit\chestshop\button\ButtonFactory;
use muqsit\chestshop\category\Category;
use muqsit\chestshop\category\CategoryConfig;
use muqsit\chestshop\category\CategoryEntry;
use muqsit\chestshop\database\Database;
use muqsit\chestshop\economy\EconomyManager;
use muqsit\chestshop\ui\ConfirmationUI;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

final class Loader extends PluginBase{

	/** @var Database */
	private $database;

	/** @var ConfirmationUI|null */
	private $confirmation_ui;

	/** @var ChestShop */
	private $chest_shop;

	public function onEnable() : void{
		$this->getLogger()->info("§aChestShop[việt hóa] v5 đã được bật!");
	    $this->getLogger()->info("§aPlugin được dịch bởi Sói");
		$this->initVirions();
		$this->database = new Database($this);

		if($this->getConfig()->getNested("confirmation-ui.enabled", false)){
			$this->confirmation_ui = new ConfirmationUI($this);
		}

		$this->chest_shop = new ChestShop($this->database);

		ButtonFactory::init($this);
		CategoryConfig::init($this);
		EconomyManager::init($this);

		$this->database->load($this->chest_shop);
	}

	private function initVirions() : void{
		if(!InvMenuHandler::isRegistered()){
			InvMenuHandler::register($this);
		}
	}

	public function onDisable() : void{
		$this->database->close();
	}

	public function getConfirmationUi() : ?ConfirmationUI{
		return $this->confirmation_ui;
	}

	public function getChestShop() : ChestShop{
		return $this->chest_shop;
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(!($sender instanceof Player)){
			$sender->sendMessage(TextFormat::RED . "Sử dụng trong game :D Hổng phải CONSOLE nha.");
			return true;
		}

		if(isset($args[0])){
			switch($args[0]){
				case "addcat":
				case "addcategory":
					if($sender->hasPermission("chestshop.command.add")){
						$button = $sender->getInventory()->getItemInHand();
						if(!$button->isNull()){
							if(isset($args[1])){
								$name = implode(" ", array_slice($args, 1));
								$success = true;
								try{
									$this->chest_shop->addCategory(new Category($name, $button));
								}catch(\InvalidArgumentException $e){
									$sender->sendMessage(TextFormat::RED . $e->getMessage());
									$success = false;
								}
								if($success){
									$sender->sendMessage(
										TextFormat::GREEN . "Thêm danh mục " . $name . TextFormat::RESET . TextFormat::GREEN . " thành công!" . TextFormat::EOL .
										TextFormat::GRAY . "Sử dụng " . TextFormat::GREEN . "/" . $label . " additem " . $name . " <giá>" . TextFormat::GRAY . " để thêm vật phẩm."
									);
								}
								return true;
							}
						}else{
							$sender->sendMessage(TextFormat::RED . "Hãy cầm một món đồ trong tay bạn. Vật phẩm đó sẽ được sử dụng làm biểu tượng trong /" . $label . ".");
							return true;
						}
					}else{
						$sender->sendMessage(TextFormat::RED . "Bạn không được phép sử dụng lệnh này.");
						return true;
					}
					$sender->sendMessage(TextFormat::RED . "Sử dụng: /" . $label . " " . $args[0] . " <tên>");
					return true;
				case "removecat":
				case "removecategory":
					if($sender->hasPermission("chestshop.command.remove")){
						if(isset($args[1])){
							$name = implode(" ", array_slice($args, 1));
							$success = true;
							try{
								$this->chest_shop->removeCategory($name);
							}catch(\InvalidArgumentException $e){
								$sender->sendMessage(TextFormat::RED . $e->getMessage());
								$success = false;
							}
							if($success){
								$sender->sendMessage(TextFormat::GREEN . "Xóa " . $name . TextFormat::RESET . TextFormat::GREEN . " thành công!");
							}
							return true;
						}
					}else{
						$sender->sendMessage(TextFormat::RED . "Bạn không được phép sử dụng lệnh này.");
						return true;
					}
					$sender->sendMessage(TextFormat::RED . "Sử dụng: /" . $label . " " . $args[0] . " <tên>");
					return true;
				case "additem":
					if($sender->hasPermission("chestshop.command.add")){
						if(isset($args[1]) && isset($args[2])){
							$category = null;
							try{
								$category = $this->chest_shop->getCategory($args[1]);
							}catch(\InvalidArgumentException $e){
								$sender->sendMessage(TextFormat::RED . $e->getMessage());
							}
							if($category !== null){
								$item = $sender->getInventory()->getItemInHand();
								if(!$item->isNull()){
									$price = (float) $args[2];
									if($price >= 0.0){
										$category->addEntry(new CategoryEntry($item, $price));
										$sender->sendMessage(TextFormat::GREEN . "Đã thêm " . $item->getName() . TextFormat::RESET . TextFormat::GREEN . " vào danh mục " . $category->getName() . "!");
									}else{
										$sender->sendMessage(TextFormat::RED . "Giá tiền không hợp lệ " . $args[2]);
									}
								}else{
									$sender->sendMessage(TextFormat::RED . "Vui lòng cầm một món đồ trong tay.");
								}
							}
							return true;
						}
					}else{
						$sender->sendMessage(TextFormat::RED . "Bạn không được phép sử dụng lệnh này.");
						return true;
					}
					$sender->sendMessage(TextFormat::RED . "Sử dụng: /" . $label . " " . $args[0] . " <danh mục> <giá>");
					return true;
				case "removeitem":
					if($sender->hasPermission("chestshop.command.remove")){
						if(isset($args[1])){
							$category = null;
							try{
								$category = $this->chest_shop->getCategory($args[1]);
							}catch(\InvalidArgumentException $e){
								$sender->sendMessage(TextFormat::RED . $e->getMessage());
							}
							if($category !== null){
								$item = $sender->getInventory()->getItemInHand();
								if(!$item->isNull()){
									$removed = $category->removeItem($item);
									if($removed > 0){
										$sender->sendMessage(TextFormat::GREEN . "Đã xóa " . $removed . " item" . ($removed > 1 ? "s" : "") . " từ danh mục " . $category->getName() . "!");
									}else{
										$sender->sendMessage(TextFormat::RED . "Không tìm thấy " . $item->getName() . TextFormat::RESET . TextFormat::GREEN . " trong danh mục " . $category->getName() . ".");
									}
								}else{
									$sender->sendMessage(TextFormat::RED . "Vui lòng cầm một món đồ trong tay.");
								}
							}
							return true;
						}
					}else{
						$sender->sendMessage(TextFormat::RED . "Bạn không được phép sử dụng lệnh này.");
						return true;
					}
					$sender->sendMessage(TextFormat::RED . "Sử dụng: /" . $label . " " . $args[0] . " <tên danh mục> <giá>");
					return true;
			}
		}

		$this->chest_shop->send($sender);
		return true;
	}
}