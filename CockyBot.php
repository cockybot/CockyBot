<?php

// Searches the USPTO's Trademark Electronic Search System (TESS) and tweets about it
// http://tmsearch.uspto.gov/
// v1.0
// copyright 2018 cockybot

//Load required files
require_once "./TESS_Session.php";
require_once './twitter-php/twitter.class.php';

// Since we focus on books, extend class to provide links to search Amazon's book
// category for existing titles similar to the mark
class BookQueryResult extends QueryResult {
    // returns a url to search for the word mark in the books category at amazon
	// e.g. http://tsdr.uspto.gov/documentviewer?caseId=sn87604348
	public function getAmazonSearchLink() {
		$affiliateTag = "&tag=dommechron-20";
		$baseUrl = "https://www.amazon.com/s/?url=search-alias%3Dstripbooks&field-keywords=";
		return  $baseUrl . urlencode($this->wordMark);
		return  $baseUrl . urlencode($this->wordMark) . $affiliateTag;
	}
	public function __construct($queryResult) {		
		$this->serialNumber = $queryResult->serialNumber;
		$this->registrationNumber = $queryResult->registrationNumber;
		$this->wordMark = $queryResult->wordMark;
	}
}

class CockyBotException extends Exception {
	// define error codes
	const ERROR_INVALID_CONSTRUCTION = 1;
	const ERROR_SETTER = 2;
	const ERROR_SN_FILE = 3;
	
	// Redefine the exception so message and code are required
	public function __construct($message, $code, Exception $previous = null) {
        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }
}

class CockyBot {
	private $twitter;	// Twitter object from twitter-php library
	private $qType;		// should be PO or FD
	private $genres;	// key => value array with genre tag keys and associated search string
	private $date;		// for making posts from specific dates - YYYYMMDD with ? wildcards
	private $usedSerialNumbersFileName; // for storing used serial numbers
	private $usedSerialNumbers; 	// array of key => value pairs of ints for already tweeted SNs
	private $makeHistoricalPost;	// affects wording of post to add date vs. just now
	
	// default search values
	private $gs = 'book OR novels NOT NEAR graphic OR "short stories"';
	private $ld = 'LIVE';	// ignore dead applications
	private $md = '"4"';	// standard character mark
	private $tm = 'Trademark OR "Collective Mark"';
	private $ic = '"009" OR "016"'; // encompasses printed books and ebooks
	
	const DELAY_BETWEEN_TWEETS = 120; // 2 min delay between tweets
	const REAL_TWEETS = false;	// set to true to make tweets
	const TWEET_IMAGES = false;	// set to true to include mark image with tweets
	
	public function __construct($twitter, $qType, $genres, $date = NULL) {
	
		date_default_timezone_set('America/New_York'); // USPTO filings use Eastern
		
		if(get_class($twitter) === "Twitter") {
			$this->twitter = $twitter;
		} else {
			$msg = "constructor parameter $twitter must be a Twitter instance";
			throw new CockyBotException($msg, CockyBotException::ERROR_INVALID_CONSTRUCTION);
		}
		try {
			self::setQueryType($qType);
		} catch (CockyBotException $e) {
			$msg = "constructor $qType parameter was invalid";
			throw new CockyBotException($msg, CockyBotException::ERROR_INVALID_CONSTRUCTION, $e);		
		}
		try {
			self::setQueryGenresArray($genres);
		} catch (CockyBotException $e) {
			$msg = "constructor $genres parameter was invalid";
			throw new CockyBotException($msg, CockyBotException::ERROR_INVALID_CONSTRUCTION);		
		}
		try {
			self::setQueryDate($date);
		} catch (CockyBotException $e) {
			$msg = "constructor $date parameter was invalid";
			throw new CockyBotException($msg, CockyBotException::ERROR_INVALID_CONSTRUCTION, $e);
		}
		self::setPreviouslyTweetedSerialNumbers();
    }
	
	// performs queries, processses results, and sends tweets
	public function run() {
		$mainQueryString = self::getMainQueryString();
		$individualGenreQStrings = self::getIndividualGenreQueries();
		
		$session = new TESS_Session();
		$session->logIn();
		$results = $session->getQueryResults($mainQueryString);
		$individualGenreResults = [];
		foreach($individualGenreQStrings as $genre => $query) {
			sleep(5);
			$individualGenreResults[$genre] = $session->getQueryResults($query);
		}
		$session->logOut();
		
		self::processResults($results, $individualGenreResults);
    }
	
	// creates an appropriate string to represent the date portion of query
	// if no date is set, default is to search within the past two weeks
	private function getQueryStringDate() {
		if($this->date) {
			return $this->date . '['.$this->qType.']';
		}
		$startDate = (new DateTime('-14 days'))->format('Ymd');
		$endDate = (new DateTime('tomorrow'))->format('Ymd');
		$qDate = '`'.$this->qType.' > '.$startDate.' < '.$endDate;
		return $qDate;
	}
	
	// parameters:
	// $dateString - a string with format YYYYMMDD (and ? wildcards)
	// If valid, parses it into Y, M, D,  Note: doubles as date validation
	// returns: array with Y, M, D compents on valid string, false on invalid
	private function parseDateArgument($dateString) {
		preg_match('/^([12?][09?][\d?]{2})([\d?]{2})([\d?]{2})$/', $dateString, $matches);
		if($matches) {
			$year = $matches[1];
			$month = $matches[2];
			$day = $matches[3];
			return [$year, $month, $day];
		} else {
			return false;
		}
	}
	
	// convenience method 
	// returns: the query string for the primary request
	private function getMainQueryString() {
		return self::getQueryStringUsingGenreString(self::genresToQString($this->genres));
	}
	
	// convenience method
	// returns: an array of query strings, one for each genre keyword
	private function getIndividualGenreQueries() {
		$individualGenreQueries = [];
		foreach($this->genres as $genreKey => $value) {
			$individualGenreQueries[$genreKey] = self::getQueryStringUsingGenreString($value);
		}
		return $individualGenreQueries;
	}
	
	// Builds an appropriate query string based on set properties
	// parameters: 
	// $genres - a string appropriate as a query for desired genres
	// 20180512[FD] AND (book OR novels NOT NEAR graphic OR "short stories")[GS] WITH (romance)[GS] 
	// SAME (("009" OR "016") WITH IC)[GS] AND ("4")[MD]  AND (LIVE)[LD]  AND (Trademark OR "Collective Mark")[TM]
	private function getQueryStringUsingGenreString($genres) {
		$q = self::getQueryStringDate();
		$q .= ' AND ('.$this->gs.')[GS]';
		$q .= ' WITH ('.self::genresToQString($this->genres).')[GS]';
		$q .= ' SAME (('.$this->ic.') WITH IC)[GS]';
		$q .= ' AND ('.$this->md.')[MD] ';
		$q .= ' AND ('.$this->ld.')[LD] ';
		$q .= ' AND ('.$this->tm.')[TM]';
		return $q;
	}
	
	// setters
	public function setQueryGenresArray($genres) {
		if( is_array($genres) && array() !== $genres) {
			$this->genres = $genres;
		} else {
			$msg = "Attempt to set invalid genre array: " . $qType;
			throw new CockyBotException($msg, CockyBotException::ERROR_SETTER);		
		}
	}
	
	public function setQueryType($qType) {
		if($qType === "PO" || $qType === "FD") {
			$this->qType = $qType;
		} else {
			$msg = "Attempt to set invalid query type: " . $qType;
			throw new CockyBotException($msg, CockyBotException::ERROR_SETTER);
		}
	}
	
	public function setQueryDate($date) {
		if($date && self::parseDateArgument($date)) {
			$this->date = $date; //TODO
			$this->makeHistoricalPost = true;
		} else {
			if($date !== NULL && !self::parseDateArgument($date)) {
				$msg = "Attempt to set invalid date: " . $date;
				throw new CockyBotException($msg, CockyBotException::ERROR_SETTER);	
			}
			$this->date = NULL;
		}
	}
	// parameter: $ic - search string with list of internal class codes, separated by or
	// e.g. $ic = '"009" OR "016"';
	// [IC] field should be three digit codes, as strings, in range of 001 to 045
	// TESS recommends numbers be enclosed in quotes, this enforces that policy
	public function setQueryInternationalClasses($ic) {
		if(preg_match('/^"\d{3}"(?: OR "\d{3}")*$/i', $ic)) {
			if(preg_match_all('/"(\d{3})"/', $ic, $matches)) {
				foreach ($matches[1] as $match) {
					if(intval($match) >= 1 && intval($match) <= 45) {
						$this->ic = $ic;
						return;
					}
				}
			}
		}
		$msg = "Attempt to set invalid international class: " . $ic;
		throw new CockyBotException($msg, CockyBotException::ERROR_SETTER);	
	}
	
	// parameter: $gs search string to use as restrictive part of goods and services search
	// can't do too much validation, but do check for matching quotes error and non-empty
	public function setQueryRequiredGoodsAndServices($gs) {
		if(substr_count($gs, '"') % 2 == 0 && strlen($gs) > 0) {
			$this->gs = $gs;
		} else {
			$msg = "Attempt to set invalid goods and services search string: " . $gs;
			throw new CockyBotException($msg, CockyBotException::ERROR_SETTER);				
		}
	}
	
	// parameter: string with LIVE or DEAD status of application
	public function setQueryLiveStatus($ld) {
		if($ld == "LIVE" || $ld == "DEAD") {
			$this->ld = $ld;
		} else {
			$msg = "Attempt to set invalid value for live/dead status: " . $ld;
			throw new CockyBotException($msg, CockyBotException::ERROR_SETTER);
		}
	}
	
	// parameter: $md - string with number for mark drawing code encloded in double quotes
	// e.g. $md = '"4"'; valid codes are 0-6
	public function setQueryMarkDrawingCode($md) {
		if(preg_match('/^"[0-6]"(?: OR "[0-6]")*$/i', $md) ) {
			$this->md = $md;
		} else {
			$msg = "Attempt to set invalid mark drawing code: " . $md;
			throw new CockyBotException($msg, CockyBotException::ERROR_SETTER);
		}
	}
	
	public function setQueryTypeOfMark($tm) {
		$group = '("?trademark"?|"service mark"|"collective mark"|"collective membership mark"|"certification mark")';
		if( preg_match('/^'.$group.'( or '.$group.')*$/i', $tm) ) {
			$this->tm = $tm;
			return;
		} else {
			$msg = "Attempt to set invalid type of mark: " . $tm;
			throw new CockyBotException($msg, CockyBotException::ERROR_SETTER);
		}
	}
	
	// Does the work of tweeting off new results, takes no action on ones already sent
	// parameters:
	// $results - the array of hits from the main query
	// $individualGenreResults - and array with each element corresponding to one
	// of the genre tags and holding an array of any hits with that tag's keyword query   
	private function processResults($results, $individualGenreResults) {
		$resultIndex = 0;
		$tweetCount = 0;
		foreach($results as $result) {
			$bookResult = new BookQueryResult($result);
			// only do new results that haven't been previously tweeted
			if(!isset( $this->usedSerialNumbers[intval($result->serialNumber)] )) {
				if($resultIndex != 0 && self::REAL_TWEETS) {
					sleep(self::DELAY_BETWEEN_TWEETS);
				}
				$genreList = self::createGenreListForResult($result, $individualGenreResults);
				$this->usedSerialNumbers[] = intval($result->serialNumber);
				file_put_contents($this->usedSerialNumbersFileName, $result->serialNumber."\n", FILE_APPEND);
				self::tweetNotice($bookResult, $genreList);
				$tweetCount++;
			}
			$resultIndex++;
		}
		echo "\nTweeted " . $tweetCount . " of " . count($results) . " found.\n";
	}

	// Load up an array with all previously tweeted serial numbers from an appropriate file
	// separate files are maintained for PO and FD queries
	// there are also separate files for debugging, so the production files don't get 
	// stuff dumped into them while just testing
	// note: since everything is in memory, this isn't super scalable, but probably fine
	// for a reasonable bot
	private function setPreviouslyTweetedSerialNumbers() {
		$usedSerialNumbers = [];
		$usedSerialNumbersFileName = "";
		if($this->qType === "PO") {
			$usedSerialNumbersFileName = (self::REAL_TWEETS ? "tweeted_po_sns.txt" : "tweeted_po_sns_debug.txt");
		} else if($this->qType === "FD") {
			$usedSerialNumbersFileName = (self::REAL_TWEETS ? "tweeted_fd_sns.txt" : "tweeted_fd_sns_debug.txt");
		} else {
			error_log("Didn't recognize query type: " . $this->qType);
			exit(3);
		}
		//read in all serial numbers that have already been tweeted
		if (file_exists($usedSerialNumbersFileName)) {
			echo "Reading used serial numbers from file " .$usedSerialNumbersFileName ."…\n";
			$usedNumberFile = fopen($usedSerialNumbersFileName, 'r');
			while ($usedNumberFile && !feof($usedNumberFile)) {
				$line = fgets($usedNumberFile);
				// verify it's an 8-digit serial number or blank (should be last line only)
				if( !preg_match('/^(\d{8})|()$/',$line) ) {
					$msg = "invalid line in ".$usedSerialNumbersFileName.": ".$line;
					throw new CockyBotException($msg, CockyBotException::ERROR_SN_FILE);
				}
				$lineValue = intval($line);
				// store with serial number as key to allow constant time lookup
				$usedSerialNumbers[$lineValue] = $lineValue;
			}
			fclose($usedNumberFile);
		}
		$this->usedSerialNumbersFileName = $usedSerialNumbersFileName;
		$this->usedSerialNumbers = $usedSerialNumbers;
	}

	// creates a string with a comma-separated list of matching genres for a given result 
	// parameters: 
	// $result - an individual QueryResult instance
	// $individualGenreResults - the array of genre tags holding arrays with associated hits
	// returns: string with comma-separated of genre tags with queries results that 
	// included the specified $result  
	private function createGenreListForResult($result, $individualGenreResults) {
		$matchCount = 0;
		foreach ($individualGenreResults as $genre => $results) {
			if(in_array($result, $results)) {
				if(!$str) $str = $genre;
				else $str .= ", " . $genre;
				$matchCount++;
			}
		}
		if($matchCount > 4) {
			return "multiple";
		}
		if($matchCount == 0) {
			return "";
		}
		return $str;
	}

	// Compose tweet text and content and then send it to Twitter
	// parameters: 
	// $result - the BookQueryResult to tweet about
	// $genrelist - the list of genre keywords it matched on - maybe should make property of BookQueryResult?
	private function tweetNotice($result, $genreList) {
		$historical = $this->makeHistoricalPost;
		$messsage = "";
		if($historical) $message .= "From my ".self::formatQueryDate($this->date)." memory bank:\n";
		if($this->qType === "PO") {
			$message .= "An application to trademark “" . $result->wordMark;
			$message .= "” was ".($historical?"":"just ")."published for opposition.";
		}
		if($this->qType === "FD") {
			$message .= "An application to trademark “" . $result->wordMark;
			$message .= "” was ".($historical?"":"just ")."filed.";
		}	
		$message .= "\nCheck the links below to view more information:\n";
		$message .= "Status: " . $result->getShareableStatusLink() . "\n";
		$message .= "Documents: " . $result->getShareableDocumentLink() . "\n";
		$message .= "AMZN: " . $result->getAmazonSearchLink() . "\n";
		$message .= "keywords: " . $genreList;
		echo "\n" . $message . "\n";
	
		$imageFilePath = "./tmp_img.png";
		$imgSet = $result->saveImageAsFile($imageFilePath);

		if(self::REAL_TWEETS) {
			try {
				if($imgSet && self::TWEET_IMAGES) {
					$tweet = $this->twitter->send($message, $imageFilePath);
				} else {
					$tweet = $this->twitter->send($message);
				}
			} catch (TwitterException $e) {
				echo 'Error: ' . $e->getMessage();
			}
		}
	}

	// Formats timestamps j M Y for inclusion in historical tweets
	private function formatQueryDate($date) {
		$dateComponents = self::parseDateArgument($date);
		$y = $dateComponents[0];
		$m = $dateComponents[1];
		$d = $dateComponents[2];
		$format = 'j M Y';
		if( preg_match('/[?]/', $d) ) {
			$format = 'M Y';
			$d = 1;
		}
		if( preg_match('/[?]/', $m) ) {
			$format = 'Y';
			$m = 1;
		}
		if( preg_match('/[?]/', $y) ) {
			return $y;
		}
		$timestamp  = mktime(0, 0, 0, intval($m) , intval($d), intval($y));
		$date = new DateTime("@$timestamp");
		return $date->format($format); // e.g. 1 Jan 2018 (no leading zero on days)
	}

	// takes an array of genres search strings and concatenates them with ORs between
	private function genresToQString($genreArray) {
		$i=0;
		foreach($genreArray as $genre) {
			if($i == 0) {
				$str = $genre; $i++;
			} else {
				$str .= " OR " .$genre; // OR is optional, but makes more human readable
			}
		}
		return $str;
	}

}