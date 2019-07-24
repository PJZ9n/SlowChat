<?php
    
    /*This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.*/
    
    declare(strict_types=1);
    
    namespace PJZ9n\SlowChat;
    
    use pocketmine\event\Listener;
    use pocketmine\event\player\PlayerChatEvent;
    use pocketmine\event\player\PlayerLoginEvent;
    use pocketmine\event\player\PlayerQuitEvent;
    use pocketmine\Player;
    use pocketmine\plugin\PluginBase;
    use pocketmine\scheduler\Task;
    use pocketmine\Server;
    use pocketmine\utils\Config;
    use pocketmine\utils\TextFormat;
    
    /**
     * Class Main
     * @package PJZ9n\SlowChat
     */
    class Main extends PluginBase implements Listener
    {
        //XXX: 思い付きで作った適当で最悪なコード！！！！！！
        
        /**
         * 最後にチャットした時間
         * 単位: ミリ秒
         * @var int[]
         */
        public $lastChatTime = [];
        
        /**
         * チャットのキュー
         * @var string|null[] REVIEW: ここの書き方が微妙
         */
        public $chatQueue = [];
        
        /**
         * イベントを無視するかどうか
         * @var bool[]
         */
        public $eventIgnore = [];
        
        /**
         * プラグインが有効化した時
         */
        public function onEnable(): void
        {
            $this->getServer()->getPluginManager()->registerEvents($this, $this);
            new Config($this->getDataFolder() . "config.yml", Config::YAML, [
                "send-time" => 2,
                "check-time" => 1000,
            ]);
            $this->reloadConfig();
            
            $task = new class($this) extends Task
            {
                
                /** @var Main */
                private $main;
                
                /**
                 * constructor.
                 * @param Main $main
                 */
                public function __construct(Main $main)
                {
                    $this->main = $main;
                }
                
                /**
                 * run
                 * @param int $currentTick
                 */
                public function onRun(int $currentTick): void
                {
                    foreach ($this->main->chatQueue as $name => $messsage) {
                        if ($messsage === null) {
                            return;
                        }
                        $player = Server::getInstance()->getPlayer($name);
                        if (!$player instanceof Player) {
                            return;
                        }
                        $this->main->chatQueue[$name] = null;
                        $this->main->lastChatTime[$name] = Main::millis();
                        $this->main->eventIgnore[$name] = true;
                        $player->chat(strval($messsage));
                    }
                }
                
            };
            $this->getScheduler()->scheduleRepeatingTask($task, 20 * $this->getConfig()->get("send-time"));
        }
        
        /**
         * ログイン時
         * @param PlayerLoginEvent $event
         * @allowHandle
         * @priority NORMAL
         */
        public function onLogin(PlayerLoginEvent $event): void
        {
            $player = $event->getPlayer();
            $name = $player->getName();
            $this->lastChatTime[$name] = null;
            $this->chatQueue[$name] = null;
            $this->eventIgnore[$name] = false;
        }
        
        /**
         * ログアウト時
         * @param PlayerQuitEvent $event
         * @allowHandle
         * @priority NORMAL
         */
        public function onQuit(PlayerQuitEvent $event): void
        {
            $player = $event->getPlayer();
            $name = $player->getName();
            unset($this->lastChatTime[$name]);
            unset($this->chatQueue[$name]);
            unset($this->eventIgnore[$name]);
        }
        
        /**
         * チャット時
         * @param PlayerChatEvent $event
         * @allowHandle
         * @priority NORMAL
         */
        public function onChat(PlayerChatEvent $event): void
        {
            $player = $event->getPlayer();
            $name = $player->getName();
            $message = $event->getMessage();
            if ($this->eventIgnore[$name]) {
                $this->eventIgnore[$name] = false;
                return;
            }
            if ($this->chatQueue[$name] !== null) {
                $player->sendMessage(TextFormat::RED . "発言はもうしばらくお待ちください。");
                $event->setCancelled();
                return;
            }
            if ($this->lastChatTime[$name] === null) {
                $this->lastChatTime[$name] = self::millis();
                return;
            }
            if (self::millis() - $this->lastChatTime[$name] < intval($this->getConfig()->get("check-time"))) {
                $this->chatQueue[$name] = $message;
                $player->sendMessage(TextFormat::RED . "チャットの間隔が短すぎるため発言が遅延します。");
                $event->setCancelled();
                return;
            }
            $this->lastChatTime[$name] = self::millis();
        }
        
        /**
         * 現在のUNIXミリ秒を返す
         * @return int
         */
        public static function millis(): int
        {
            return intval(floor(microtime(true) * 1000));
        }
        
    }