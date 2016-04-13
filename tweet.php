<?php
if (file_exists(__DIR__ . '/settings.php')) {
	$settings = require __DIR__ . '/settings.php';
}else{
	echo 'Missing settings file.';
	exit();
}
/*
	PHP scripts are launched via crontab,
	1.	scan.php launched once at 8am on Thursday.
		. checks on http://www.ge.ch/registre_foncier/publications-foncieres.asp to get transactions with a price (Prix total de l'affaire) greater than X
		. build tweets and store them on DB
	2.	tweet.php launched every 15mim from 8am on Thursday.
		scan DB and send one tweet
*/
	header('Content-Type: text/html; charset=utf-8');

	// Select one tweet that needs to be sent
	$db = new SQLite3($settings['db']);
    $sql = 'SELECT * FROM "immo_alert" WHERE sent=0 order by message_date, itweet ASC limit 1';
	$result = $db->query($sql);
	if ($res = $result->fetchArray(SQLITE3_ASSOC)) {
		tweet($res, $db);
	}

	/**
		Sends a tweet
	*/
	function tweet($row, $db) {
		require 'tmhOAuth/tmhOAuth.php'; 	// Include tweeter lib
		$tmhOAuth = new tmhOAuth(array(		// tweeter authentification
			'consumer_key' => $settings['consumer_key'],
			'consumer_secret' => $settings['consumer_secret'],
			'token' => $settings['token'],
			'secret' => $settings['secret'],
		));
		$tmhOAuth->request('POST', $tmhOAuth->url('1.1/statuses/update'), array(
			'status' => $row['message']
		));

		if ($tmhOAuth->response['code'] == 200) {
			$err_msg = 'OK';
			$req = 'UPDATE immo_alert
				SET sent = 1, sent_result = "'.$err_msg.'"
				WHERE message_date="'.$row['message_date'].'" AND itweet='.$row['itweet'].' AND sent=0';
			$db->exec($req);
		}
		else {
			$err_msg = json_decode($tmhOAuth->response['response'])->errors[0]->message;
			$req = 'UPDATE immo_alert
				SET sent = 1, sent_result = "'.$err_msg.'"
				WHERE message_date="'.$row['message_date'].'" AND itweet='.$row['itweet'].' AND sent=0';
			$db->exec($req);
		}

		if(isset($_GET['debug'])) {
			print_r($row);
			echo '<hr>';
			echo $err_msg;
			echo '<hr>'.$req.'<hr>';
		}
	}

