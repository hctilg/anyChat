<?php

flush();
ob_start();
ob_implicit_flush(1);

/********************** Importing Requirements **********************/

$telebot_path = __DIR__ . '/telebot@2.0.php';

// checking the exists "Telebot Library".
if (!file_exists($telebot_path)) {
  copy('https://raw.githubusercontent.com/hctilg/telebot/v2.0/index.php', $telebot_path);
}

// import telebot library
require_once $telebot_path;

/********************* Configs for Telegram Bot *********************/

// Get it from @BotFather
define("TOKEN", "");

// Get it from @UserInfoBot
define("CREATOR", "");

/*********************** Main Section of Code ***********************/

class JsonBase{
  public $file_db;

  public function __construct(string $file){
    if (!file_exists($file)) {
      $_file = fopen($file,'w+');
      fwrite($_file, '');
      fclose($_file);
    }
    $this->file_db = $file;
  }

  public function get(){
    $read_file = file_get_contents($this->file_db);
    return json_decode($read_file, true);
  }

  public function commit(Array $data){
    $myfile = fopen($this->file_db, "w") or die("Unable to open file!");
    fwrite($myfile, json_encode($data));
    fclose($myfile);
  }

  public function clear() {
    $this->commit([]);
  }
}

$bot = new Telebot(TOKEN, false);

$bot->on('all', function($type, $data) use ($bot) {
  $chat_type = $data['chat']['type'] ?? $data['chat_type'] ?? 'unknown';
  $chat_id = $data['chat']['id'] ?? $data['from']['id'] ?? 'unknown';
  $msg_id = $data['message_id'] ?? -1;
  $text = $data['text'] ?? '';
    
  if ($chat_type != 'private' || $chat_id == 'unknown') return;
    
  if (!is_dir('data')) mkdir("data");
  if (!file_exists('data/step.txt')) file_put_contents("data/step.txt", 'default');
  if (!file_exists('data/block.txt')) file_put_contents("data/block.txt", '[]');
  
  $blocklist = new JsonBase("data/block.txt");
  if (in_array($chat_id, $blocklist->get())) {
    $bot->sendMessage([ 'chat_id'=> $chat_id, 'text'=> "You're blocked.", 'reply_to_message_id'=> $msg_id ]);
    return;
  }
  
  if ($chat_id == CREATOR) {
    $step = file_get_contents("data/step.txt");
    if ($step == 'default') $bot->sendMessage([ 'chat_id'=> $chat_id, 'text'=> "Welcome ^^", 'reply_to_message_id'=> $msg_id ]);
    else {
      file_put_contents("data/step.txt", 'default');
      $conf = explode(':', $step);
      $user_id = $conf[0];
      $user_msg_id = $conf[1];
      $bot->sendMessage(['chat_id'=> $chat_id, 'text'=> "✅", 'reply_to_message_id'=> $msg_id]);
      $bot->sendMessage(['chat_id'=> $user_id, 'text'=> "🔥 New Message 👇🏻", 'reply_to_message_id'=> $user_msg_id]);
      $bot->copyMessage(['chat_id'=> $user_id, 'from_chat_id'=> $chat_id, 'message_id'=> $msg_id]);
    }
    return;
  }
    
  if (startsWith('/start', $text)) {
    $bot->sendMessage(['chat_id'=> $chat_id, 'text'=> "Hey babe, message me  :)", 'reply_to_message_id'=> $msg_id]);
    return;
  }
  
  $user_hash = base64_encode(md5($chat_id, true));
  $res = $bot->copyMessage(['chat_id'=> CREATOR, 'from_chat_id'=> $chat_id, 'message_id'=> $msg_id]);
  $msg = $res['result'];
  
  $bot->sendMessage(['chat_id'=> $chat_id, 'text'=> "Whenever I see it, I'll reply...️", 'reply_to_message_id'=> $msg_id]);
  $bot->sendMessage([
    'chat_id'=> CREATOR,
    'text'=> "New ($type) Message 👆🏻\nUHID: $user_hash",
    'reply_markup'=> Telebot::inline_keyboard("[Block ⛔️|block_$chat_id][Reply ✍🏻|reply_$chat_id:$msg_id]"),
    'reply_to_message_id'=> $msg['message_id']
  ]);

});

$bot->on('callback_query', function($callback_query) use ($bot) {
  $query_id = $callback_query['id'];
  $query_data = $callback_query['data'];
  $chat_id = $callback_query['message']['chat']['id'];
  $msg_id = $callback_query['message']['message_id'];
  $keyboard = $callback_query['message']['reply_markup'];
  $text = $callback_query['message']['text'] ?? '';

  if (startsWith('reply_', $query_data)) {
    $conf = substr($query_data, strlen('reply_'));
    file_put_contents("data/step.txt", $conf);
    $bot->sendMessage(['chat_id'=> CREATOR, 'text'=> "Send Message :", 'reply_to_message_id'=> $msg_id]);
  } elseif (startsWith('block_', $query_data)) {
    $user_id = substr($query_data, strlen('block_'));
    $blocklist = new JsonBase("data/block.txt");
    $block_array = $blocklist->get();
    if (!in_array($user_id, $block_array)) {
      $block_array[] = $user_id;
      $blocklist->commit($block_array);
    }
    
    $bot->editMessageText([
      'chat_id'=> $chat_id,
      'message_id'=> $msg_id,
      'text'=> $text,
      'reply_markup'=> Telebot::inline_keyboard("[Unblock ⛔️|unblock_$user_id][Reply ✍🏻|reply_$user_id:$msg_id]")
    ]);
  } elseif (startsWith('unblock_', $query_data)) {
    $user_id = substr($query_data, strlen('unblock_'));
    $blocklist = new JsonBase("data/block.txt");
    $block_array = $blocklist->get();
    if (in_array($user_id, $block_array)) {
      if (($key = array_search($user_id, $block_array)) !== false) unset($block_array[$key]);
      $blocklist->commit($block_array);
    }
    $bot->editMessageText([
      'chat_id'=> $chat_id,
      'message_id'=> $msg_id,
      'text'=> $text,
      'reply_markup'=> Telebot::inline_keyboard("[Block ⛔️|block_$user_id][Reply ✍🏻|reply_$user_id:$msg_id]")
    ]);
  }
});

$bot->run();
