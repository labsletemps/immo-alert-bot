<?php
if (file_exists(__DIR__ . '/settings.php')) {
	$settings = require __DIR__ . '/settings.php';
}else{
	echo 'Missing settings file.';
	exit();
}
	header('Content-Type: text/html; charset=utf-8');

	$db = new SQLite3($settings['db']);

	//-------------------------------
	// URL & Default parameters
	//-------------------------------
	echoDebug("<strong>Paramètres possibles:</strong>
				<ul>
					<li><strong>priceAlert</strong>: montant de transaction à partir duquel l'alerte est donnée (5'000'000 si non renseignée)</li>
					<li><strong>fromDate</strong>: Date de début (YYYY-MM-JJ) date du jour si non renseignée</li>
					<li><strong>toDate</strong>: Date de fin (YYYY-MM-JJ)  date du jour moins un semaine si non renseignée</li>
				</ul><hr>");

	// Minimum amount for alert:
	if (isset($_GET['priceAlert']) && !empty($_GET['priceAlert'])) {
		$priceAlert = $_GET['priceAlert'];

	}
	else {
		$priceAlert = 5000000; // amount from which we'll send an alert
	}
	$priceAlertMillions = round($priceAlert/1000000, 0, PHP_ROUND_HALF_DOWN); // to be able to output "5" millions

	// L'intervalle de recherche est limité à 60 jours ge.ch
	// From date (ex: 2016-12-31)
	if (isset($_GET['fromDate']) && !empty($_GET['fromDate'])) {
		$fromDate = explode('-', $_GET['fromDate']);
	}
	else {	// last week
		$fromDate = explode('-', date('Y-n-j', strtotime("-7 days")));
	}

	// To date  (ex: 2016-12-31)
	if (isset($_GET['toDate']) && !empty($_GET['toDate'])) {
		$toDate = explode('-', $_GET['toDate']);
	}
	else {	// yesterday (since ge.ch.... updates data on wednesday)
		$toDate = explode('-',date('Y-n-j', strtotime("-1 day")));
	}

	echoDebug('<strong>Paramètres envoyés:</strong> '.$priceAlert .' CHF ');
	echoDebug('du '.$fromDate[0].'-'.$fromDate[1].'-'.$fromDate[2]);
	echoDebug(' au '.$toDate[0].'-'.$toDate[1].'-'.$toDate[2].'<hr>');


	//-------------------------------
	// Variables
	//-------------------------------
	$url_immo_xml = 'https://www.letemps.ch/taxonomy/term/566/feed';	// XML theme "immobilier" on www.letemps.ch
	$results = [];									// To store annoncements we want to tweet
	$strMatch = "'<td colspan=4>(.*?)</td>'si";		// dom nodes on which results are.
	$strBeforePrice = "Prix total de l'affaire "; 	// text right before the information we need
	$strAfterPrice = ".";							// text right after the information we need (we get rid of centimes)
	$strAfterDate = ' - ';							// text separator between date and city.
	$strAfterCity = ', ';							// text separator between city and rest of text.
	// $tweets // defined below since wee need included price, city .... to be defined.


	//----------------------------
	// Main
	//----------------------------

	// Get METEO content
	if (isset($settings['wunderground_api']) && !empty($settings['wunderground_api'])) {
		$urlMeteo = 'http://api.wunderground.com/api/' . $settings['wunderground_api'] . '/conditions/q/CA/geneve.json';
		$meteo = file_get_contents($urlMeteo);
		if (false===$meteo) {
			echoDebug('Erreur de lecture URL: '.$urlMeteo);
			exit;
		}
		$meteo = json_decode($meteo);
		$meteo = $meteo->current_observation->weather;
		$meteoTranslation = [
			'chanceflurries' => '💨 Avis de gros vent sur le lac de Genève ce matin.',
			'chancerain' => 	'☔ Il risque de pleuvoir sur Genève, pensez à votre parapluie.',
			'chancesleet' => 	'❄️ Prudence! Risque de pluie verglaçante sur Genève.',
			'chancesleet' => 	'❄️ Prudence: risque de grésil ce matin sur Genève.',
			'chancesnow' => 	'❄️ La neige pourrait s’inviter ce matin sur Genève.',
			'chancetstorms' => 	'⚡ Risques d’orages aujourd’hui sur Genève.',
			'clear' => 			'☀️ Le ciel est dégagé ce matin sur Genève.',
			'cloudy' => 		'☁️ Ciel voilé ce matin sur Genève.',
			'flurries' => 		'️💨 Des bourrasques balaient le lac de Genève ce matin.',
			'fog' => 			'☁️ Genève se réveille dans le brouillard ce matin.',
			'hazy' => 			'☁️ Genève est brumeuse ce matin.',
			'mostlycloudy' => 	'⛅️ Soleil timide ce matin sur Genève.',
			'mostlysunny' => 	'☀️ Large soleil ce matin sur Genève.',
			'partlycloudy' => 	'⛅ Soleil un peu timide ce matin sur Genève.',
			'partlysunny' => 	'⛅ Une belle journée s’annonce aujourd’hui à Genève.',
			//'sleet' => 			'❄️ Attention, pluies verglaçantes sur Genève ce matin.',
			'lightrain' => 		'💧Pluie légère sur Genève, sortez couvert.',
			'rain' => 			'💧Il pleut sur Genève, sortez couvert.',
			'sleet' => 			'❄️ Attention, chutes de neige fondue sur Genève.',
			'snow' => 			'❄️ Genève est sous la neige ce matin.',
			'sunny' => 			'☀️ Grand ciel bleu ce matin à Genève.',
			'tstorms' => 		'⚡️Le tonnerre gronde ce matin sur Genève.',
			'unknown' => 		'',
			'cloudy' => 		'☁️ Ciel couvert ce matin sur Genève.',
			'partlycloudy' => 	'⛅️ Quelques nuages ce matin sur Genève.',
		];
			// Some other possible values found on web
			//	here https://wordpress.org/support/topic/plugin-weather-forecast-wp-wunderground-weather-forecast-wunderground
			//	and here https://www.wunderground.com/weather/api/d/docs?d=resources/phrase-glossary


		echoDebug('METEO: ' . $meteo . ' -> ');
		if (isset($meteoTranslation[strtolower(str_replace(' ', '', $meteo))])) {
			$meteo = $meteoTranslation[strtolower(str_replace(' ', '', $meteo))];
		}
		else {
			$meteo = '';
		}
		echoDebug($meteo . '<hr>');
	} // end if $settings['wunderground_api']

	
	// Get captcha results
	$content = file_get_contents('https://www.ge.ch/registre_foncier/publications-foncieres.asp');

	$dom = new \DOMDocument('1.0', 'UTF-8');
	libxml_use_internal_errors(true) and libxml_clear_errors();
	$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOWARNING);


	$ef_captchacontrole = '';
	$labels = $dom->getElementsByTagName('label');
	foreach($labels as $label) {
		if ($label->getAttribute('for') == 'ef_captchacontrole') {
			if (preg_match("/(\d)\s\+\s(\d)/i", $label->textContent, $matches)) {
				echoDebug("Chiffres du captcha: " . print_r($matches, 1) . "<hr>");
				$ef_captchacontrole = (int) $matches[1] + (int) $matches[2];
			}
		}
	}

	$ef_captcharesultcontrole = '';
	$tid = '';
	$inputs = $dom->getElementsByTagName('input');
	foreach ($inputs as $input) {
		if ($input->getAttribute('name') == 'ef_captcharesultcontrole') {
			$ef_captcharesultcontrole = $input->getAttribute('value');
			echoDebug("Captcha result controle: " . $ef_captcharesultcontrole . "<hr>");
		}

		if ($input->getAttribute('name') == 'tid') {
			$tid = $input->getAttribute('value');
			echoDebug("Captcha TID: " . $tid . "<hr>");
		}
	}

	// Get distant page REGISTRE IMMOBILIER GENEVE content
	$query = array (
		'query' => 'showallresult',
		'num' => '0',
		'jourDeDate' => $fromDate[2],
		'moisDeDate' => $fromDate[1],
		'anneeDeDate' => $fromDate[0],
		'jourADate' => $toDate[2],
		'moisADate' => $toDate[1],
		'anneeADate' => $toDate[0],
		'commune' => '',
		'texte' => '',
		'ef_captchacontrole' => $ef_captchacontrole,
		'tid' => $tid - 10,
		'ef_captcharesultcontrole' => $ef_captcharesultcontrole,
		'rechercher' => 'Rechercher',
	);
	$query = http_build_query($query);
	$url_search = "https://www.ge.ch/registre_foncier/publications-foncieres.asp?" . $query;
	echoDebug('<a target="_blank" href="'.$url_search.'"">'.$url_search.'</a><hr>');

	$attempt = 0;
	$source = false;
	$captchaError = false;
	$agent= 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
	while (!$source && ++$attempt < 10 && $captchaError===false) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, $agent);
		curl_setopt($ch, CURLOPT_URL,$url_search);
		$source = curl_exec($ch);
		curl_close($ch);

		if (false === $source) {
			echoDebug('Erreur de lecture URL: '.$url_search. ' - Tentative '.$attempt.'<br>');
		}
		
		if (false !== $captchaError = strpos($source, "Vous n'avez pas rempli correctement le champ de calcul")) {
			echoDebug("Erreur de calcul ?: " . $captchaErr . "tentative ".$attempt."<hr>");
		}
	}
	if ($attempt >= 10) {
		echoDebug("10 tentatives <hr>");
		die();
	}


	// Parse text
	preg_match_all($strMatch, $source, $match);
	echoDebug(count($match[1]) . ' lignes<hr>');
	if($match) {
		echoDebug('<ol>');
		foreach ($match[1] as $m) {

			$m = iconv ( 'ISO-8859-1' , 'UTF-8' , $m);	// www.ge.ch is ISO-8859-1 encoded
			$posAfterDate = strpos($m, $strAfterDate);
			$posAfterCity = strpos($m, $strAfterCity);

			$date = substr($m, 0, $posAfterDate);
			$city = substr($m, $posAfterDate+strlen($strAfterDate), $posAfterCity-$posAfterDate-strlen($strAfterDate));

			// Search price
			$p = strpos($m, $strBeforePrice);
			if ($p !== false) {

				// Get price
				$priceTxt = substr($m, $p+strlen($strBeforePrice));
				$p2 = strpos($priceTxt, $strAfterPrice);
				$priceTxt = substr($priceTxt, 0, $p2);
				$price = (int)str_replace("'", "", $priceTxt);

				// Keep track of &texte field to get a more precise search result. NB Single quote are not allowed by ge.ch
				$textToSearch = 'affaire ' . substr($priceTxt, 0, strpos($priceTxt, "'"));

				if ($price >= $priceAlert) {
					//echoDebug('<span style="color:#ff0000">' . $price . '</span><br>');
					echoDebug('<li><span style="color:#0000ff">'. $m .'</span><br><br></li>');
					$results[] = array(	// $results[$price] = array()
						'city'=> $city,
						'date'=> $date,
						'priceTxt' => $priceTxt,
						'price' => $price,
						'textToSearch' => $textToSearch);
				}
				//echoDebug('<span style="color:#0000ff">'. $m .'</span><hr>');		// transaction
			}
			else {
				//echoDebug('<span style="color:#555555">'. $m . '</span><hr>');	// annonce sans transaction
			}

			// Search buyer in order to build link to specific transaction
		}
		echoDebug('</ol><hr>');
	}
	else {
		echoDebug("Content not found");
		exit;
	}


	// Send alerts via tweeterbot ...

	// Definition of tweets and included variables
	$transactions = count($results);
	$xml = simplexml_load_file($url_immo_xml);
	function sortByAmountDesc($a, $b) {
	    return $b['price'] - $a['price'];
	}
	usort($results, 'sortByAmountDesc'); // Sort results by price desc

	$tmpErrorReporting = error_reporting();
	error_reporting(E_ALL & ~E_NOTICE);	// avoid notices if we don't have enough results for all tweets (ex: "Notice: Undefined offset: 2" if we have only 2 results)
	$tweets = [	// Tweets contents, with variants so messages are not always the same.
		[
			'msg' => [
				"Bonjour! $meteo Comme chaque mercredi, voici notre bilan du marché immobilier."],
			'link' => ""
		],/*[
			'msg' => [
				"Cette semaine, $transactions transaction(s) de plus $priceAlertMillions millions de francs a/ont été enregistrée(s) dans le canton de Genève",
				//"Voici les $transactions plus grosses transactions immobilières, plus de $priceAlertMillions millions de francs, sur Genève cette semaine",
				"$transactions: c’est le nombre de transactions immobillères à plus de $priceAlertMillions millions de francs enregistrées cette semaine à #Genève" ],
			'link' => ""
		],*/[
			'msg' => [
				"La transaction record de la semaine: une vente à {$results[0]['priceTxt']} CHF à {$results[0]['city']}",
				"La vente de la semaine: une transaction de {$results[0]['priceTxt']} francs à {$results[0]['city']}",
				"Pour un montant de {$results[0]['priceTxt']} CHF, cette transaction à {$results[0]['city']} est la plus importante de la semaine" ],
			'link' => '',	// link for transactionnal tweet will be generated later on
			'transactionnal' => true
		],[
			'msg' => [
				"Deuxième vente de la semaine: une transaction à {$results[1]['priceTxt']} francs a été enregistrée à {$results[1]['city']}",
				"La seconde vente de la semaine a été enregistrée à {$results[1]['city']} pour un montant de {$results[1]['priceTxt']} CHF",
				"En numéro 2, une vente à {$results[1]['city']} à {$results[1]['priceTxt']} CHF" ],
			'link'  => '',	// link for transactionnal tweet will be generated later on
			'transactionnal' => true
		],[
			'msg' => [
				"Troisième vente la plus importante de la semaine: une transaction de {$results[2]['priceTxt']} francs enregistrée à {$results[2]['city']}",
				"La troisième vente de la semaine a été réalisée à {$results[2]['city']} pour un montant de {$results[2]['priceTxt']} CHF",
				"En numéro 3, une vente à {$results[2]['city']} à {$results[2]['priceTxt']} CHF" ],
			'link'  => '',	// link for transactionnal tweet will be generated later on
			'transactionnal' => true
		],[
			'msg' => [
				"Pour plus de détails, voici l’intégralité des ventes enregistrées au registre foncier du canton de Genève",
				"Retrouvez ici toutes les transactions foncières, sur le site du registre foncier du canton de Genève",
				"Vous souhaitez plus de détails? Toutes les transactions sont en ligne sur le site du registre foncier de Genève" ],
			'link' => '',//"$url_search"
		],[
			'msg' => [
				"Notre dernier article immo: «{$xml->channel->item[0]->title}»",
				"A relire sur @letemps: «{$xml->channel->item[0]->title}»" ],
			'link' => "{$xml->channel->item[0]->link}"
		]
	];

	$noTransactionTweet = 	// tweet that will be sent when no transaction found
		"Aucune transaction de plus de $priceAlertMillions millions de francs sur Genève cette semaine";

	// $tweets = array_reverse($tweets); // since twitter output in anti chronological order
	error_reporting($tmpErrorReporting);



	// Send our tweets
	echoDebug('<hr><h2>Tweets:</h2>');


	$sendNoTransactionFoundTweet = ($transactions > 0) ? false : true; // shall we sent "Aucune transaction" tweet ?
	$iTransactionnal = 0;
	$firstTransactionnalTweet = false;
	foreach ($tweets as $iTweet => $t) {

		// Normal tweet (no transactionnal)
		if (!isset($t['transactionnal']))  {
			// check if last one (article immobilier sur letemps.ch) has not been tweet last weeks
			if (false !== strpos($t['link'], 'www.letemps.ch')) {
				// Look in DB if that tweet has already been sent
	        	$sql = 'SELECT * FROM "immo_alert" WHERE message LIKE "%' . $t['link'] .'%" AND sent = 1';
				$result = $db->query($sql);
				if (!$res = $result->fetchArray(SQLITE3_ASSOC)) {
					stackTweet($t['msg'][rand( 0, count($t['msg'])-1 )], $t['link'], $iTweet, $db);
				}
			}
			else {
				stackTweet($t['msg'][rand( 0, count($t['msg'])-1 )], $t['link'], $iTweet, $db);
			}
		}

		// Transactionnal tweet && result found
		else if($transactions) {

			if (false === $firstTransactionnalTweet) {
				$firstTransactionnalTweet = $iTweet;
			}
			$iTransactionnal++;

			if ($transactions >= $iTransactionnal) { // Send transactional tweets only if we have enough results

				$curResult = $results[$iTweet-$firstTransactionnalTweet];
				$itemDate = explode('.', $curResult['date']); // date got from ge.ch: 31.12.2016
				/*
				$t['link'] = 'http://www.ge.ch/registre_foncier/publications-foncieres.asp?query=showallresult&operation='
				. "&texte=".urlencode($curResult['textToSearch'])
				. '&jourDeDate='.$itemDate[0].'&moisDeDate='.$itemDate[1].'&anneeDeDate='.$itemDate[2]
				. '&jourADate='.$itemDate[0].'&moisADate='.$itemDate[1].'&anneeADate='.$itemDate[2]
				. '&commune='. urlencode( iconv( 'UTF-8' ,  'Windows-1252', str_replace('Genève-', '', $curResult['city'])));
				*/
				$t['link'] = ''; // Because of captcha, don't provide links anymore
				stackTweet($t['msg'][rand( 0, count($t['msg'])-1 )], $t['link'], $iTweet, $db);
			}
		}

		// Transactionnal tweet && NO result found
		else if ($sendNoTransactionFoundTweet) {
			$sendNoTransactionFoundTweet = false;
			stackTweet($noTransactionTweet, '', $iTweet, $db);
		}
	}

	$db->close();
	// end Main



	//---------------------------------------------------------------------------------------------------------
	//	Fuctions
	//---------------------------------------------------------------------------------------------------------

	function echoDebug($msg) {
		if(isset($_GET['debug'])) {
			echo $msg;
		}
	}

	/**
		Store tweets that need to be sent after fixing tweet length
	*/
	function stackTweet($message, $url='', $iTweet, $db) {
		// NB: Une URL, quelle qu'en soit la longueur, sera systématiquement modifiée pour compter 23 caractères https://support.twitter.com/articles/20045438
		// faut juste faire gaffe qu'elle soit bien url-encodée; un espace et c'est mort.
		// 140 char max -23 for url -1 for space between text an URL = 116
		if ($url) {
			if (strlen($message) > 116) {
				$message = substr($message, 0, 113).'...';
			}
			if (isset($_GET['debug'])) {
				$message .= ' <a target="_blank" href="'. $url .'">http://TwitterURL23char</a>';
			}
			else {
				// Add timestamp so tweet is new each single time
				$query = parse_url($url, PHP_URL_QUERY);
				if ($query) {
				    $url .= '&t='.time();
				} else {
				    $url .= '?t='.time();
				}
				$message .= ' '.$url;
			}
		}
		else if (strlen($message) > 140) {
			$message = substr($message, 0, 137).'...';
		}


		if(isset($_GET['debug'])) {
			echo $message.'<hr>';
			return;
		}

		$db->exec('insert into immo_alert (message_date, message, itweet, sent) values(
			"' . date('Y-m-d H:i:s') . '",
			"' . $message . '" ,
			'  . $iTweet . ' ,
			0 )');	// 0 = Tweet has not yet been sent
	}

