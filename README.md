![alt tag](https://pbs.twimg.com/profile_banners/712662225589284865/1458765748/1500x500)

## Immo Alerte GE


@Immo_Alerte est un projet du quotidien suisse Le Temps. Ce TwitterBot repère et tweete tous les jeudis matin les transactions immobilières supérieures à 5 millions de francs suisses à Genève, en s'appuyant sur les données publiques du registre foncier cantonal. Il donne aussi la météo du jour, grâce à l'API de Wunderground. 

Le TwitterBot: https://twitter.com/immo_alerte

scan.php & tweet.php
--------------------
PHP scripts are launched via crontab,
1.  scan.php launched once at 8am on Thursday.
  . checks on http://www.ge.ch/registre_foncier/publications-foncieres.asp to get transactions with a price (Prix total de l'affaire) greater than X
  . build tweets and store them on DB
2.  tweet.php launched every 15mim from 8am on Thursday.
  scan DB and send tweets


SQLite Schema
-------------
```sql
CREATE TABLE 'immo_alert' (
 'sent' DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 'message' TEXT NOT NULL,
 'itweet' INTEGER NOT NULL DEFAULT 0 ,
 'sent_status' BOOLEAN NOT NULL DEFAULT 0 ,
 'sent_error_msg' TEXT,
 PRIMARY KEY ('sent', 'message'))');
```
