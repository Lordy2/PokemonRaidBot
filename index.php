<?php
// Parent dir.
$parent = __DIR__;

// Include requirements and perfom initial steps
include_once(__DIR__ . '/core/bot/requirements.php');

// Start logging.
debug_log("RAID-BOT '" . $config->BOT_ID . "'");

// Check API Key and get input from telegram
include_once(CORE_BOT_PATH . '/apikey.php');

// We maybe receive a webhook so far...
$webhook = false;
foreach ($update as $raid) {

    if (isset($raid['type']) && $raid['type'] == 'raid') {
    
        $webhook = true;
        break;    
    }
}

if ($webhook === false) {
    
    // DDOS protection
    include_once(CORE_BOT_PATH . '/ddos.php');
}

// Get language
include_once(CORE_BOT_PATH . '/userlanguage.php');

// Database connection
include_once(CORE_BOT_PATH . '/db.php');

// Run cleanup if requested
include_once(CORE_BOT_PATH . '/cleanup_run.php');

if ($webhook === true) {
    
    // Create raid(s) and exit.
    include_once(ROOT_PATH . '/commands/raid_from_webhook.php');
    $dbh = null;
    exit();
}

// Update the user
update_user($update);

// Callback query received.
if (isset($update['callback_query'])) {
    // Logic to get the module
    include_once(CORE_BOT_PATH . '/modules.php');

// Inline query received.
} else if (isset($update['inline_query'])) {
    // List quests and exit.
    raid_list($update);
    $dbh = null;
    exit();

// Location received.
} else if (isset($update['message']['location']) && $update['message']['chat']['type'] == 'private') {
    // Create raid and exit.
    include_once(ROOT_PATH . '/mods/raid_by_location.php');
    $dbh = null;
    exit();

// Cleanup collection from channel/supergroup messages.
} else if ((isset($update['channel_post']) && $update['channel_post']['chat']['type'] == "channel") || (isset($update['message']) && $update['message']['chat']['type'] == "supergroup")) {
    // Collect cleanup information
    include_once(CORE_BOT_PATH . '/cleanup_collect.php');
    chat_log($update);
// Message is required to check for commands.
} else if (isset($update['message']) && ($update['message']['chat']['type'] == 'private' || $update['message']['chat']['type'] == 'channel')) {
    // Portal message?
    if(isset($update['message']['entities']['1']['type']) && $update['message']['entities']['1']['type'] == 'text_link' && strpos($update['message']['entities']['1']['url'], 'https://intel.ingress.com/intel?ll=') === 0) {
        // Import portal.
        include_once(ROOT_PATH . '/mods/importal.php');
    } else {
        // Check if user is expected to be posting something we want to save to db
        $q = my_query("SELECT id FROM raids WHERE event_note='{$update['message']['from']['id']}'");
        debug_log("Found user from raids table: ".$update['message']['from']['id']);
        if($q->num_rows > 0) {
			$res = $q->fetch_assoc();
            
            $dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
			$query = $dbh->prepare("UPDATE raids SET event_note=:text WHERE id = '{$res['id']}'");
			$query->execute(array(':text' => $update['message']['text']));
            
			$msg = '';
			$msg .= getTranslation('raid_saved') . CR;
			$msg .= CR.'Note: '.$update['message']['text'].CR2;
			$msg .= show_raid_poll_small(get_raid($res['id'])) . CR;
			debug_log($msg);
			$keys = [
				[
					[
						'text'          => 'Muokkaa tekstiä',
						'callback_data' => $res['id'] . ':edit_event_note:edit'
					]
				],
				[
					[
						'text'          => getTranslation('delete'),
						'callback_data' => $res['id'] . ':raids_delete:0'
					]
				]
			];
            $keys_share = share_keys($res['id'], 'raid_share', $update, $chats);
			$keys = array_merge($keys, $keys_share);
            
            debug_log($keys);
			send_message($update['message']['from']['id'],$msg,$keys,[]);
        }else {
            // Logic to get the command
            include_once(CORE_BOT_PATH . '/commands.php');
        }
    }
}

$dbh = null;
?>
