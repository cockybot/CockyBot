<?php

// CockyBot Run Script
// Demo showing illustrative run settings
// copyright 2018 cockybot

//Load required files
require_once "./CockyBot.php";

// TWITTER CREDENTIALS – NEVER PLACE THIS FILE IN A PUBLIC LOCATION!
// Get yours from: https://apps.twitter.com/
$consumerKey = "XXXXXXXXXXXXXXXXXXXXXXXXX";
$consumerSecret = "XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX";
$accessToken = "XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX";
$accessTokenSecret = "XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX";

$twitter = new Twitter($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);

// Select type of query to run:
// PO - date of publication
// FD - initial filing date
// $qType = "PO";
$qType = "FD";

// List of genres and associated keyword searches to run
// comment out lines to exclude them from search
$genres = [
	"adventure" => 'adventure',
// 	"audio" => '"audio books"',
	"children's" => '"children s books"',
// 	"comics" => '"comic books"',
//	"contemporary" => '"contemporary fiction"',
	"crime" => 'crime',
// 	"detective" => 'detective',
// 	"dragon" => 'dragon',
	"drama" => 'drama',
	"erotica" => 'erotic OR erotica', //'erotic$',
	"fairy tales" => '"fairy tales"',
	"fantasy" => 'fantasy',
	"fiction" => 'fiction NOT NEAR non OR fictional',
// 	"graphic novels" => '"graphic novels"',
	"historical" => '"historical fiction"',
	"horror" => 'horror',
//	"literary" => '"literary fiction"',
	"mystery" => 'mystery',
//	"mythology" => 'mythology',
// 	"non-fiction" => '"non fiction"',
	"novels" => '"novels NOT NEAR graphic"',
	"paranormal" => 'paranormal',
	"poetry" => 'poetry',
	"publishing house" => '"books on a variety of topics"',
	"romance" => 'romance',
	"sci-fi" => '"science fiction"',
	"short stories" => '"short stories"',
	"suspense" => 'suspense',
// 	"thriller" => 'thriller',
// 	"tragedy" => 'tragedy',
// 	"werewolves" => 'werewolves',
//	"women's" => '"women s fiction"',
	"young adult" => '"young adult" OR teen',
];
$genres = ["romance" => "romance"];

// Search for a specific past date
// note: for dates, '?' is treated as a single digit wildcard
$qYear = "2018";
$qMonth = "0?";
$qDay = "??";
// $date = $qYear.$qMonth.$qDay;

// OR search for a range of the past two weeks:
$date = NULL;

$bot = new CockyBot($twitter, $qType, $genres, $date);

// these are the default settings, but can optionally be changed
$bot->setQueryGenresArray($genres);
$bot->setQueryType($qType);
$bot->setQueryDate($date);
$bot->setQueryRequiredGoodsAndServices('book OR novels NOT NEAR graphic OR "short stories"');
$bot->setQueryLiveStatus('LIVE');
$bot->setQueryMarkDrawingCode('"4"');
$bot->setQueryTypeOfMark('Trademark OR "Collective Mark"');
$bot->setQueryInternationalClasses('"009" OR "016"');
$bot->run();
exit(0);

// General notes on TESS Free Form query strings:
// constructed with boolean logic and parenthetical grouping for order of operations
// nesting is permitted, OR is implicit between terms if no operator specified
// relevant fields:
// [PO]	- date of publication for opposition
// [FD] - date of initial filing
// [GS] - description of goods and services
// [MD] - type of mark; "4" corresponds to a standard character mark
// 		  i.e. not design or style, just text, the only type we search for
// [LD] - live or dead status of the trademark filing, we only search for live
// [TM] - type of mark, we limit to trade mark and collective marks
// [IC] - the international classification for the goods the mark covers 
// 		  we limit to "016" ("Paper Goods and Printed Matter", which includes print books)
// 		  and "009" - ("Electrical and Scientific Apparatus", which includes ebooks)

?>
