<?php

// CockyBot Run Script
// Script to run a CockyBot instance focused on fiction
// v1.0
// copyright 2018 cockybot
// https://cockybot.com

// USAGE:
//
// php demo-run.php [-t|type FD] [-d|date YYYYMMDD] [-r|real] [-i|img]
//
// Options:
//
// -t	Specify type of search, FD for filing date or PO for publication for opposition.
//		FD is the deafult if not specified.
// -d	Specify a date string for search, in format YYYYMMDD, with ? as single digit 
//		wildcards. Default is to search the last two weeks if not specified.
// -r	Specify to use real tweets. If not set, script will run in testing mode with
//		output logged, but not actually tweeted.
// -i	Specify to include an image of the mark in the tweet.  If not set, images
//		will not be used.

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
// comment/uncomment lines to exclude/include them from search
$genres = [
	"adventure" => '(books or novels) ADJ "in the field of" ADJ10 adventure',
// 	"audio" => '"audio books"',
// 	"children's" => '"children s books"',
// 	"comics" => '"comic books"',
//	"contemporary" => '"contemporary fiction"',
	"crime" => '(books or novels) ADJ "in the field of" ADJ10 crime',
	"dark" => '(books or novels) ADJ "in the field of" ADJ10 dark',
// 	"detective" => 'detective',
// 	"dragon" => 'dragon',
	"drama" => '(books or novels) ADJ "in the field of" ADJ10 drama',
	"erotica" => 'erotic OR erotica', //'erotic$',
	"fairy tales" => '"fairy tales"',
	"fantasy" => '(books or novels) ADJ "in the field of" ADJ10 fantasy',
	"fiction" => 'fiction NOT NEAR non OR fictional',
// 	"graphic novels" => '"graphic novels"',
	"historical" => '"historical fiction"',
	"horror" => '(books or novels) ADJ "in the field of" ADJ10 horror',
//	"literary" => '"literary fiction"',
	"mystery" => '(books or novels) ADJ "in the field of" ADJ10 mystery',
	"mythology" => '(books or novels) ADJ "in the field of" ADJ10 mythology',
// 	"non-fiction" => '"non fiction"',
	"novels" => 'novels NOT NEAR graphic',
	"paranormal" => '(books or novels) ADJ "in the field of" ADJ10 paranormal',
	"poetry" => 'poetry',
	"publishing house" => '"books on a variety of topics" NOT NEAR2 "non fiction"',
	"romance" => 'romance',
	"sci-fi" => '(books or novels) ADJ "in the field of" ADJ10 "science fiction"',
	"short stories" => '"short stories"',
	"suspense" => '(books or novels) ADJ "in the field of" ADJ10 suspense',
	"thriller" => '(books or novels) ADJ "in the field of" ADJ10 thriller',
// 	"tragedy" => 'tragedy',
// 	"werewolves" => 'werewolves',
	"women's" => '"women s fiction"',
	"young adult" => '(books or novels) ADJ "in the field of" ADJ10 ("young adult" OR teen)',
];
// $genres = ["romance" => "romance"];

// Search for a specific past date
// note: for dates, '?' is treated as a single digit wildcard
$qYear = "2018";
$qMonth = "0?";
$qDay = "??";
$date = $qYear.$qMonth.$qDay;

// OR search for a range of the past two weeks:
$date = NULL;

// whether or not to use real tweets for the run, or just test
$useRealTweets = false;

// whether or not to include images in tweets
$tweetImages = false;

// use command line launch arguments
parseLaunchArguments($argv, $qType, $date, $useRealTweets, $tweetImages);

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

// run currently configred query
$bot->run($useRealTweets, $tweetImages);

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

// parse the arguments passed in from command line launch and set values appropriately
function parseLaunchArguments($argv, &$qType, &$date, &$useRealTweets, &$tweetImages) {
	if(count($argv) > 1) {
		for($i = 1; $i<count($argv); $i++) {
			if($argv[$i] === "-t" || $argv[$i] === "-type") {
				if($argv[$i+1] === "PO") {
					echo "Performing search of publications for opposition.\n";
					$i++;
					$qType = "PO";
				} elseif($argv[$i+1] === "FD") {
					echo "Performing search of new filings.\n";
					$i++;
					$qType = "FD";
				} else {
					echo "Invalid argument '".$argv[$i+1]."' given for query type (-t flag).\n";
				}
			} elseif ($argv[$i] === "-d" || $argv[$i] === "-date") {
				if( CockyBot::parseDateArgument($argv[$i+1]) ) {
					echo "Searching for date: ".$argv[$i+1]."\n";
					$date = $argv[$i+1];
					$i++;
				} else {
					echo "Invalid argument '".$argv[$i+1]."' given for date (-d flag).\n";
				}
			} elseif($argv[$i] === "-r" || $argv[$i] === "-real") {
				echo "Using real tweets\n";
				$useRealTweets = true;
			} elseif($argv[$i] === "-i" || $argv[$i] === "-img") {
				echo "Using images in tweets\n";
				$tweetImages = true;
			} else {
				echo "Invalid argument: ".$argv[$i]."\n";
			}
		}
	}
}

?>
