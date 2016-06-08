<?php
	$db = new SQLite3('db/twitterbot.db');

	if (isset($_POST['tweets']) && !empty($_POST['tweets'])) {
		$tweets = $_POST['tweets'];

		foreach($tweets as $tweet) {
			$query = sprintf('insert into immo_alert (message_date, message, itweet, sent, attached_image) values("%s", "%s", %d, %d, "%s");', date('Y-m-d H:i:s'), $tweet['message'], $tweet['itweet'], 0, $tweet['attached_image']);
			$db->exec($query);
		}
	}

	$db->close();
	unset($db);

//var_dump($_POST['tweets']);