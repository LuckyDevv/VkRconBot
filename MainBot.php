<?php
$data = json_decode(file_get_contents('php://input'));
require_once 'modules/Rcon.php';
require_once 'modules/ConfigController.php';
use modules\{Rcon, ConfigController};
new MainBot($data);
class MainBot{
    private $data;
    private Rcon $rcon;
    private \SQLite3 $db;
    private ConfigController $toCp;
    private array $defaultButtons = [];
    private string $token = 'vk1.a.Vjd4jyPxrgeV2G_cGFRLdKYd14-pYzxVdmplGgB0FXZut2_rRbN4eEr7LrZndiznI0XIgyMkdsJL7FIarbOzEGU_-BbxmzXBQq6J8LTT6qyQeGgN6BKk3TL3NVE3TDkEC5e5-OKMhZkAZ8P0LJlw5wMGzRrhbsJJRAS5pPyliYhMdzArtwq6mTNxQfpJA8pKg1aQ1Hb0wVLC0yE4s-U0hg';
    public function __construct($data){
        $this->data = $data;
        $this->rcon = new Rcon('194.67.206.232', 19138, 'TxDplG8pXb', 5);
        @mkdir(__DIR__.'/data');
        $this->toCp = new ConfigController(__DIR__.'/data/toChangePassword.json', 2);
        $this->db = new \SQLite3(__DIR__.'/data/Users.db');
        $this->db->exec("CREATE TABLE IF NOT EXISTS bonus (vkid TEXT PRIMARY KEY, time INTEGER);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS regBonus (vkid TEXT PRIMARY KEY);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS users (vkid TEXT PRIMARY KEY, account TEXT);");
        $this->defaultButtons = [[$this->setButton('🎁 Бонус', 'primary', 'give_bonus'), $this->setButton('🛡 Снять защиту', 'default', 'restore_protect')], [$this->setButton('🔑 Сменить пароль', 'negative', 'change_password'), $this->setButton('👨‍💻 Профиль', 'primary', 'profile')]];
        $this->onEnable();
    }
/*
███╗░░░███╗░█████╗░██╗███╗░░██╗        ███████╗██╗░░░██╗███╗░░██╗░█████╗░████████╗██╗░█████╗░███╗░░██╗░██████╗
████╗░████║██╔══██╗██║████╗░██║        ██╔════╝██║░░░██║████╗░██║██╔══██╗╚══██╔══╝██║██╔══██╗████╗░██║██╔════╝
██╔████╔██║███████║██║██╔██╗██║        █████╗░░██║░░░██║██╔██╗██║██║░░╚═╝░░░██║░░░██║██║░░██║██╔██╗██║╚█████╗░
██║╚██╔╝██║██╔══██║██║██║╚████║        ██╔══╝░░██║░░░██║██║╚████║██║░░██╗░░░██║░░░██║██║░░██║██║╚████║░╚═══██╗
██║░╚═╝░██║██║░░██║██║██║░╚███║        ██║░░░░░╚██████╔╝██║░╚███║╚█████╔╝░░░██║░░░██║╚█████╔╝██║░╚███║██████╔╝
╚═╝░░░░░╚═╝╚═╝░░╚═╝╚═╝╚═╝░░╚══╝        ╚═╝░░░░░░╚═════╝░╚═╝░░╚══╝░╚════╝░░░░╚═╝░░░╚═╝░╚════╝░╚═╝░░╚══╝╚═════╝░
*/
    private function onEnable(){
        switch($this->data->type){
            case 'confirmation':
                $this->onConfirm();
                break;
            case 'message_new':
                if(isset($this->data->object->message->payload)){
                    $payload = json_decode($this->data->object->message->payload, JSON_UNESCAPED_UNICODE);
                }else $payload = false;
                $from_id = $this->data->object->message->from_id;
                $peer_id =  $this->data->object->message->peer_id;
                if(!$this->rcon->connect()){
                    $this->sendMessage('Не удалось подключиться к серверу!', $from_id);
                    header("HTTP/1.1 200 OK");
                    die('OK');
                }
                if($payload){
                    $this->onButton($payload, $from_id, $peer_id);
                }else{
                    $args = explode(' ', $this->data->object->message->text);
                    $cmd = $args[0];
                    array_shift($args);
                    $this->onCommandText($cmd, $from_id, $peer_id, $args);
                }
                $this->sendOk();
                break;
            case 'like_add':
                $this->onLike($this->data->object->liker_id, $this->data->object->object_id);
                $this->sendOk();
                break;
            default:
                $this->sendOk();
                break;
        }
    }
    private function sendOk(){
        header("HTTP/1.1 200 OK");
        echo 'OK';
    }
    private function sendConfirm(){
        die('345a174a');
    }
    private function setButton($label, $color = 'default', $payload = ''): array{
        return
            [
                'action' => [
                    'type' => 'text',
                    'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    'label' => $label
                ],
                'color' => $color
            ];
    }
    private function sendButton(string $message, $peer_id, array $buttons = [], $inline = false){
        $this->sendParams(array(
            'message' => $message,
            'keyboard' => json_encode(
                [
                    'inline' => $inline,
                    'one_time' => false,
                    'buttons' => $buttons
                ],
                JSON_UNESCAPED_UNICODE),
            'peer_id' => $peer_id,
            'access_token' => $this->token,
            'random_id' => rand(1, 1000000),
            'read_state' => 1,
            'v' => '5.131'
        ));
    }
    private function sendMessage($msg, $peer_id) {
        $this->sendParams(array(
            'message' => $msg,
            'keyboard' => json_encode(
                [
                    'inline' => false,
                    'one_time' => false,
                    'buttons' => []
                ],
                JSON_UNESCAPED_UNICODE),
            'peer_id' => $peer_id,
            'access_token' => $this->token,
            'random_id' => rand(1, 1000000),
            'read_state' => 1,
            'v' => '5.131'
        ));
    }
    private function sendAllMessage($msg, $ids) {
        $this->sendParams(array(
            'message' => $msg,
            'user_ids' => $ids,
            'access_token' => $this->token,
            'random_id' => rand(1, 1000000),
            'read_state' => 1,
            'v' => '5.131'
        ));
    }
    private function sendParams($request_params){
        file_get_contents('https://api.vk.com/method/messages.send?' . http_build_query($request_params));
    }
    private function existsBind(int $id): bool{
        $query = $this->db->query("SELECT * FROM users WHERE vkid='$id';");
        if($query !== false){
            $query = $query->fetchArray(1);
            if(is_array($query)){
                return (count($query) > 0 && isset($query['vkid']) && $query['vkid'] == "$id");
            }
        }
        return false;
    }
    private function getNick(int $id){
        $query = $this->db->query("SELECT * FROM users WHERE vkid='$id';")->fetchArray(1);
        return $query['account'];
    }
    private function mathTime(int $seconds): string{
        $res = [];
        $res['days'] = floor($seconds / 86400);
        $res['hours'] = floor($seconds / 3600);
        $res['minutes'] = floor(($seconds / 60) % 60);
        $res['secs'] = $seconds % 60;
        return rtrim(($res['days'] != 0 ? $res['days'].' дн., ' : '').($res['hours'] != 0 ? $res['hours'].' ч., ' : '').($res['minutes'] != 0 ? $res['minutes'].' м., ' : '').($res['secs'] != 0 ? $res['secs'].' сек., ' : ''), ', ');
    }
/*
██╗░░░██╗░██████╗███████╗██████╗░░██████╗       ███████╗██╗░░░██╗███╗░░██╗░█████╗░████████╗██╗░█████╗░███╗░░██╗░██████╗
██║░░░██║██╔════╝██╔════╝██╔══██╗██╔════╝       ██╔════╝██║░░░██║████╗░██║██╔══██╗╚══██╔══╝██║██╔══██╗████╗░██║██╔════╝
██║░░░██║╚█████╗░█████╗░░██████╔╝╚█████╗░       █████╗░░██║░░░██║██╔██╗██║██║░░╚═╝░░░██║░░░██║██║░░██║██╔██╗██║╚█████╗░
██║░░░██║░╚═══██╗██╔══╝░░██╔══██╗░╚═══██╗       ██╔══╝░░██║░░░██║██║╚████║██║░░██╗░░░██║░░░██║██║░░██║██║╚████║░╚═══██╗
╚██████╔╝██████╔╝███████╗██║░░██║██████╔╝       ██║░░░░░╚██████╔╝██║░╚███║╚█████╔╝░░░██║░░░██║╚█████╔╝██║░╚███║██████╔╝
░╚═════╝░╚═════╝░╚══════╝╚═╝░░╚═╝╚═════╝░       ╚═╝░░░░░░╚═════╝░╚═╝░░╚══╝░╚════╝░░░░╚═╝░░░╚═╝░╚════╝░╚═╝░░╚══╝╚═════╝░
*/
    private function onConfirm(){
        $this->sendConfirm();
    }
    private function onCommandText(string $command, int $from_id, int $peer_id, array $args){
        if(mb_strtolower($command, "UTF-8") == '/vkcode'){
            if(!$this->existsBind($from_id)){
                if(isset($args[1])){
                    if(is_numeric($args[1])){
                        $nick = strtolower($args[0]);
                        $request = $this->rcon->send_command('vkbot uauth '.$nick.' '.$args[1]);
                        switch($request){
                            case 'OK':
                                if($this->db->exec("INSERT INTO users (vkid, account) VALUES ('$from_id', '".$args[0]."');")){
                                    $this->db->exec("INSERT INTO regBonus (vkid) VALUES ('$from_id');");
                                    $prepare = $this->db->prepare("INSERT INTO bonus (vkid, time) VALUES ('$from_id', :time);");
                                    $prepare->bindValue(':time', 0);
                                    $prepare->execute();
                                    $this->rcon->send_command('vkbot addmoney '.$args[0].' 25000');
                                    $this->sendButton('✅ » Вы успешно привязали аккаунт '.$args[0].' к своей странице ВКонтакте!'."\n".'🎁 » Также бонусом вы получаете 25.000$ за привязку!', $from_id, $this->defaultButtons);
                                }else $this->sendMessage('❌ » Не удалось связаться с базой данных!', $from_id);
                                break;
                            case 'NO_CODE':
                                $this->sendMessage('❌ » Вы ввели неверный код подтверждения! Попробуйте ещё раз!', $from_id);
                                break;
                            case 'NO_REQ':
                                $this->sendMessage('❌ » Вы не запрашивали код подтверждения для привязки ко ВКонтакте!', $from_id);
                                break;
                        }
                    }else $this->sendMessage('💡 » Код подтверждения должен быть числом!', $from_id);
                }else $this->sendMessage('💡 » Используйте: /vkcode [ник вашего аккаунта] [код подтверждения]', $from_id);
            }else $this->sendButton('❌ » Ваш профиль ВКонтакте уже привязан к аккаунту на сервере!', $from_id, $this->defaultButtons);
        }else{
            $get = $this->toCp->get($from_id);
            if(is_array($get) && isset($get['step'])){
                if($get['step'] == 'one'){
                    $this->toCp->set($from_id, ['step' => 'two', 'oldPass' => $command, 'newPass' => '']);
                    $this->toCp->save();
                    $this->sendButton('💡 » Теперь введите новый пароль!', $from_id, [[$this->setButton('❌ Отмена', 'negative', 'quit_cp')]]);
                }elseif($get['step'] == 'two' && isset($get['oldPass'])){
                    if(strtolower($command) !== $command){
                        if(strlen($command) >= 8){
                            $this->toCp->set($from_id, ['step' => 'three', 'oldPass' => $get['oldPass'], 'newPass' => $command]);
                            $this->toCp->save();
                            $this->sendButton('💡 » Теперь нажмите на кнопку "Подтвердить" для смены пароля!', $from_id, [[$this->setButton('✅ Подтвердить', 'primary', 'confirm_password'), $this->setButton('❌ Отмена', 'negative', 'quit_cp')]]);
                        }else $this->sendButton('💡 » Пароль должен состоять минимум из 8 символов!', $from_id, [[$this->setButton('❌ Отмена', 'negative', 'quit_cp')]]);
                    }else $this->sendButton('💡 » В пароле должна быть хотя бы одна заглавная буква!', $from_id, [[$this->setButton('❌ Отмена', 'negative', 'quit_cp')]]);
                }elseif($get['step'] == 'two' && isset($get['oldPass'], $get['newPass'])){
                    $this->sendButton('💡 » Теперь нажмите на кнопку "Подтвердить" для смены пароля!', $from_id, [[$this->setButton('✅ Подтвердить', 'primary', 'confirm_password'), $this->setButton('❌ Отмена', 'negative', 'quit_cp')]]);
                }
            }else{
                if($this->existsBind($from_id)){
                    $this->sendButton('💡 » Выберите одно из предложенных действий:', $from_id, $this->defaultButtons);
                }else $this->sendMessage('💡 » Сначала вы должны зарегистрироваться в боте! (/vkcode [ник-нейм] [код подтверждения])', $from_id);
            }
        }
    }
    private function onButton($payload, $from_id, $peer_id){
        switch(strtolower($payload)){
            case 'give_bonus':
                if($this->existsBind($from_id)){
                    $request = $this->db->query("SELECT * FROM bonus WHERE vkid='$from_id';");
                    if($request !== false){
                        $request = $request->fetchArray(1);
                        if(is_array($request)){
                            if(count($request) > 0 && isset($request['time'], $request['vkid'])){
                                $time = $request['time'];
                                if($time === 0 or $time < time()){
                                    $rand_moneys = mt_rand(25000, 75000);
                                    if($this->rcon->send_command('vkbot addmoney '.$this->getNick($from_id).' '.$rand_moneys) == 'OK'){
                                        $time = time() + (60 * 60 * 12);
                                        $prepare = $this->db->prepare("REPLACE INTO bonus (vkid, time) VALUES ('$from_id', :time);");
                                        $prepare->bindValue(':time', $time);
                                        $prepare->execute();
                                        $this->sendButton('🎁 » Вы успешно получили ежедневный бонус в размере '.$rand_moneys.'$!', $from_id, $this->defaultButtons);
                                    }else $this->sendButton('❌ » Не удалось выдать бонус вам на аккаунт!', $from_id, $this->defaultButtons);
                                }else $this->sendButton('⌚» Вы уже получали ежедневный бонус! Подождите ещё '.$this->mathTime($time - time()), $from_id, $this->defaultButtons);
                            }else $this->sendMessage('❌ » Вы не привязали свой аккаунт к профилю ВКонтакте! Код ошибки: 4', $from_id);
                        }else $this->sendMessage('❌ » Вы не привязали свой аккаунт к профилю ВКонтакте! Код ошибки: 3', $from_id);
                    }else $this->sendMessage('❌ » Вы не привязали свой аккаунт к профилю ВКонтакте! Код ошибки: 2', $from_id);
                }else $this->sendMessage('💡 » Сначала вы должны зарегистрироваться в боте! (/vkcode [ник-нейм] [код подтверждения])', $from_id);
                break;
            case 'back_restore':
            case 'restore_protect':
                if($this->existsBind($from_id)){
                    $this->sendButton('💡 » Выберите тип защиты, который хотите сбросить:', $from_id, [[$this->setButton('👕 По скину', 'primary', 'restore_skin'), $this->setButton('🤖 По CID', 'default', 'restore_cid')], [$this->setButton('💻 По UUID', 'default', 'restore_uuid'), $this->setButton('🔙 Назад', 'negative', 'quit')]]);
                }else $this->sendMessage('💡 » Сначала вы должны зарегистрироваться в боте! (/vkcode [ник-нейм] [код подтверждения])', $from_id);
                break;
            case 'restore_skin':
            case 'restore_cid':
            case 'restore_uuid':
                $type = explode('_', $payload)[1];
                $this->sendButton('💡 » Подтвердите сброс защиты по '.strtoupper($type).' на аккаунте!', $from_id, [[$this->setButton('✅ Подтвердить', 'primary', 'confirm_restore_'.$type), $this->setButton('🔙 Назад', 'negative', 'back_restore')]]);
                break;
            case 'confirm_restore_skin':
            case 'confirm_restore_cid':
            case 'confirm_restore_uui':
                $type = explode('_', $payload)[2];
                $this->rcon->send_command('protect restore-'.$type.' '.$this->getNick($from_id));
                $this->sendButton('✅ » Вы успешно сбросили защиту по '.strtoupper($type).' на аккаунте!', $from_id, $this->defaultButtons);
                break;
            case 'change_password':
                $this->sendButton('💡 » Введите ваш текущий пароль!', $from_id, [[$this->setButton('❌ Отмена', 'negative', 'quit_cp')]]);
                $this->toCp->set($from_id, ['step' => 'one', 'oldPass' => '', 'newPass' => '']);
                $this->toCp->save();
                break;
            case 'confirm_password':
                $get = $this->toCp->get($from_id);
                if(is_array($get) && isset($get['oldPass'], $get['newPass'])){
                    switch($this->rcon->send_command('vkbot cp '.$this->getNick($from_id).' '.$get['oldPass'].' '.$get['newPass'])){
                        case 'OK':
                            $this->sendButton('✅ » Вы успешно сменили свой пароль на '.$get['newPass'], $from_id, $this->defaultButtons);
                            break;
                        case 'NO_OLD_PASS':
                            $this->sendButton('❌ » Вы ввели неверный пароль!', $from_id, $this->defaultButtons);
                            break;
                        case 'NO_NEW_PASS':
                            $this->sendButton('❌ » Не удалось сменить пароль вашего аккаунта!', $from_id, $this->defaultButtons);
                            break;
                        case 'NO_EXISTS':
                            $this->sendButton('❌ » Ваш аккаунт не найден в базе данных!', $from_id, $this->defaultButtons);
                            break;
                    }
                    $this->toCp->remove($from_id);
                    $this->toCp->save();
                }else $this->sendButton('💡 » Выберите одно из предложенных действий:', $from_id, $this->defaultButtons);
                break;
            case 'quit_cp':
                if($this->toCp->exists($from_id)){
                    $this->toCp->remove($from_id);
                    $this->toCp->save();
                }
                $this->sendButton('💡 » Выберите одно из предложенных действий:', $from_id, $this->defaultButtons);
                break;
            case 'profile':
                if($this->existsBind($from_id)){
                    $q = $this->rcon->send_command('vkbot profile '.$this->getNick($from_id));
                    if($q != "NO_ARGS"){
                        $this->sendButton($q, $from_id, $this->defaultButtons);
                    }else $this->sendButton('💡 » Выберите одно из предложенных действий:', $from_id, $this->defaultButtons);
                }else $this->sendMessage('💡 » Сначала вы должны зарегистрироваться в боте! (/vkcode [ник-нейм] [код подтверждения])', $from_id);
                break;
            default:
                $this->sendButton('💡 » Выберите одно из предложенных действий:', $from_id, $this->defaultButtons);
                break;
        }
    }
    private function onLike(int $liker_id, int $object_id){}

}
?>