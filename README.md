# CockyBot
Twitter bot to tweet about trademark applications relevant to authors of fiction.
Searches for new trademark appliction filings and publications for opposition matching specified criteria and tweets about them.

Code used by CockyBot - https://cockybot.com and https://twitter.com/cockybot

### Example basic usage:

```php
require_once "./CockyBot.php";

$twitter = new Twitter($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);
$qType = "FD"; // run a filing date query, use "PO" for publication for opposition
// specify specific genres to look for and tag
// in each key => value pair, the key on the left is the tag
// the value on the right is the query you want to use to find results for that tag
$genres = [
  "romance" => "romance",
  "novels" => '"novels NOT NEAR graphic"',
];

$bot = new CockyBot($twitter, $qType, $genres);
$bot->run();
```
### Note: 
To create your own version, you need to make a twitter app and get keys and tokens at https://apps.twitter.com

By default actual posts to twitter are off.  Once you've tested your queries, turn on live with run method parameters:
```php
$useRealTweets = true;
$bot = new CockyBot($twitter, $qType, $genres);
$bot->run($useRealTweets);
```

### General notes on TESS query parameters used by CockyBot:
Constructed with boolean logic. 
Parenthetical grouping for order of operations is permitted.
OR is implicit between terms if no operator specified.

Search fields relevant to CockyBot:
- [PO] - date of publication for opposition
- [FD] - date of initial filing
- [GS] - description of goods and services.
- [MD] - type of mark; "4" corresponds to a standard character mark
 		  i.e. not design or style, just text, the only type we search for
- [LD] - live or dead status of the trademark filing, we only search for live
- [TM] - type of mark, we limit to trade mark and collective marks
- [IC] - the international classification for the goods the mark covers.  Note: technically part of the [GS] field.
 	- we limit to "016" ("Paper Goods and Printed Matter", which includes print books)
 	- and "009" - ("Electrical and Scientific Apparatus", which includes ebooks)

For more details, see TESS's help page.  A version that doesn't require login is mirrored here: https://cockybot.com/TESS_Help.html 

### Includes
- https://github.com/cockybot/TESS_Session
- https://github.com/cubiclesoft/ultimate-web-scraper/blob/master/support/tag_filter.php
- https://github.com/dg/twitter-php
