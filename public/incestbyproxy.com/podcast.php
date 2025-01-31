<?PHP
//
// rss2html.php RSS feed to HTML webpage script
//
// Copyright 2004,205 NotePage, Inc.
// http://www.feedforall.com
// This script may be used freely for business or personal use
// This script may not be resold in any form
//
// $Id: rss2html.php,v 1.30 2005/05/08 01:47:27 housley Exp $
//
// $Log: rss2html.php,v $
// Revision 1.30  2005/05/08 01:47:27  housley
// Add ~~~NoFutureItems~~~ (and NOFUTUREITEMS for URL)
//
// Revision 1.29  2005/05/06 01:04:56  housley
// Make sure FeedMaxItems is not larger then the number of items.
//
// Revision 1.28  2005/04/30 18:08:41  housley
// ~~~SortByPubDate~~~ will force the sorting of the items by pubDate, with the newest being first.
//
// Revision 1.27  2005/04/30 17:49:17  housley
// Allow FeedMaxItems to be negative.  A negative number will grab the last
// items
//
// Revision 1.26  2005/03/28 14:48:45  housley
// * Remove debug print statement
// * Fix the creation of the putDate for cases when the feed didn't have them, bad feed...bad feed.
//
// Revision 1.25  2005/03/23 23:22:48  housley
// Handle dc:date in RSS 2.0 feeds.  If both pubData and dc:date exist, pubDate has priority
//
// Revision 1.24  2005/03/11 12:44:44  housley
// Exit with short message if a file can't be opened.
//
// Revision 1.23  2005/03/09 15:15:41  housley
// Add copyright header
//
// Revision 1.22  2005/03/08 15:25:04  housley
// * Add ID for full version number
// * Add Log to show change logs
// * Add support for <content:encoded> and ~~~FeedContentEncoded~~~ and ~~~ ItemContentEncoded~~~
//
//

ini_set("allow_url_fopen", "1");

//
// If XLMfile is passed as part of the REQUEST_URI, then it will be used
// otherwise the the file below is used.
//$XMLfilename = "http://examlple.com/sample.xml";
$XMLfilename = "http://www.incestbyproxy.com/incestbyproxyfeed.xml";
if (isset($_REQUEST["XMLFILE"])) {
  if (stristr($_REQUEST["XMLFILE"], "file://")) {
    // Not allowed
    ;
  }
  elseif (stristr($_REQUEST["XMLFILE"], "://")) {
    // URL files are allowed
    $XMLfilename = $_REQUEST["XMLFILE"];
  } else {
    // It is local and must be in the same directory
    $XMLfilename = basename($_REQUEST["XMLFILE"]);
  }
}

//
// If TEMPLATE is passed as part of the REQUEST_URI, then it will be used
// otherwise the the file below is used.
$TEMPLATEfilename = "rss_template2.html";
if (isset($_REQUEST["TEMPLATE"])) {
  if (stristr($_REQUEST["TEMPLATE"], "file://")) {
    // Not allowed
    ;
  }
  elseif (stristr($_REQUEST["TEMPLATE"], "://")) {
    // URL files are allowed
    $TEMPLATEfilename = $_REQUEST["TEMPLATE"];
  } else {
    // It is local and must be in the same directory
    $TEMPLATEfilename = basename($_REQUEST["TEMPLATE"]);
  }
}

//
// date() function documented http://www.php.net/manual/en/function.date.php
//

$LongDateFormat = "F jS, Y";    // ie, "Jan 21st, 2004"

$ShortDateFormat = "m/d/Y";     // ie, "1/21/2004"
//$ShortDateFormat = "d/m/Y";     // ie, "21/1/2004"

$LongTimeFormat = "H:i:s T O";  // ie, "13:24:30 EDT -0400"

$ShortTimeFormat = "h:i A";     // ie, "1:24 PM"

//
// Maximum number of items to be displayed
//

$FeedMaxItems = 10000;
if (isset($_REQUEST["MAXITEMS"])) {
  $FeedMaxItems = $_REQUEST["MAXITEMS"];
}
$NoFutureItems = FALSE;
if (isset($_REQUEST["NOFUTUREITEMS"])) {
  $NoFutureItems = TRUE;
}

Function getRFDdate($datestring) {
  $year = substr($datestring, 0, 4);
  $month = substr($datestring, 5, 2);
  $day = substr($datestring, 8, 2);
  $hour = substr($datestring, 11, 2);
  $minute = substr($datestring, 14, 2);
  $second = substr($datestring, 17, 2);
  if (substr($datestring, 19, 1) == "Z") {
    $offset_hour = 0;
    $offset_minute = 0;
  } else {
    if (substr($datestring, 19, 1) == "+") {
      $offset_hour = substr($datestring, 20, 2);
      $offset_minute = substr($datestring, 23, 2);
    } else {
      $offset_hour = -1*substr($datestring, 20, 2);
      $offset_minute = -1*substr($datestring, 23, 2);
    }
  }
  return gmmktime($hour+$offset_hour, $minute+$offset_minute, $second, $month, $day, $year);
}

//
// As much as I hate globals, they are needed due to the
// recusive nature of the parser
$insidechannel = FALSE;
$level_channel = 0;
$insidechannelimage = FALSE;
$level_channelimage = 0;
$insideitem = FALSE;
$level_item = 0;

class RSSParser {
  var $gotROOT = 0;
  var $feedTYPE = "RSS";
  var $level = 0;
  var $tag = "";
  var $title = "";
  var $description = "";
  var $contentEncoded = "";
  var $link = "";
  var $guid = "";
  var $enclosureURL = "";
  var $pubdate = "";
  var $pubdateDC = "";
  var $fimageURL = "";
  var $fimageTitle = "";
  var $fimageLink = "";

  var $FeedTitle = "";
  var $FeedDescription = "";
  var $FeedContentEncoded = "";
  var $FeedLink = "";
  var $FeedPubDate = "";
  var $FeedPubDateDC = "";
  var $FeedPubDate_t = "";
  var $FeedImageURL = "";
  var $FeedImageTitle = "";
  var $FeedImageLink = "";
  // When adding new Item elements, be sure to update the sort below
  var $ItemTitle = "";
  var $ItemDescription = "";
  var $ItemContentEncoded = "";
  var $ItemLink = "";
  var $ItemGuid = "";
  var $ItemPubDate = "";
  var $ItemPubDate_t = "";
  var $ItemEnclosureURL = "";

  function startElement($parser, $tagName, $attrs) {
    GLOBAL $insidechannel;
    GLOBAL $level_channel;
    GLOBAL $insidechannelimage;
    GLOBAL $level_channelimage;
    GLOBAL $insideitem;
    GLOBAL $level_item;

    $this->level++;
    $this->tag = $tagName;
    if ($this->gotROOT == 0) {
      $this->gotROOT = 1;
      if (strstr($tagName, "RSS")) {
        $this->feedTYPE = "RSS";
      }
      elseif (strstr($tagName, "RDF")) {
        $this->feedTYPE = "RDF";
      }
      elseif (strstr($tagName, "FEE")) {
        $this->feedTYPE = "FEE";
        $insidechannel = TRUE;
        $level_channel = 1;
      }
    }
    elseif ((($tagName == "ITEM") && ($this->feedTYPE != "FEE")) || (($tagName == "ENTRY") && ($this->feedTYPE == "FEE"))) {
      $insideitem = TRUE;
      $level_item = $this->level;
    }
    elseif (($insideitem) && ($tagName == "ENCLOSURE")) {
      $this->enclosureURL = $attrs["URL"];
    }
    elseif (($tagName == "LINK") && ($this->feedTYPE == "FEE")) {
      $this->link = $attrs["HREF"];
    }
    elseif ($tagName == "CHANNEL") {
      $insidechannel = TRUE;
      $level_channel = $this->level;
    }
    elseif (($tagName == "IMAGE") && ($insidechannel = TRUE)) {
      $insidechannelimage = TRUE;
      $level_channelimage = $this->level;
    }
  }

  function endElement($parser, $tagName) {
    GLOBAL $insidechannel;
    GLOBAL $level_channel;
    GLOBAL $insidechannelimage;
    GLOBAL $level_channelimage;
    GLOBAL $insideitem;
    GLOBAL $level_item;
    GLOBAL $NoFutureItems;

    $this->level--;
    if ((($tagName == "ITEM") && ($this->feedTYPE != "FEE")) || (($tagName == "ENTRY") && ($this->feedTYPE == "FEE"))) {
      $UseItem = TRUE;

      if ($NoFutureItems) {
        $noon = strtotime("today at 12:00");
        if (trim($this->pubdate) != "") {
          $ItemPubDate = strtotime($this->pubdate);
        }
        else if (trim($this->pubdateDC) != "") {
          $ItemPubDate = getRFDdate($this->pubdateDC);
        } else {
          $ItemPubDate = time();
        }
        if (($ItemPubDate - $noon) > 43200) {
          $UseItem = FALSE;
        }
      }

      if ($UseItem) {
        $this->ItemTitle[] = trim($this->title);
        $this->ItemDescription[] = trim($this->description);
        $this->ItemContentEncoded[] = trim($this->contentEncoded);
        if ($this->ItemContentEncoded == "") {
          $this->ItemContentEncoded = $this->ItemDescription;
        }
        $this->ItemLink[] = trim($this->link);
        //
        // Get the pubDate from pubDate first and then dc:date
        if (trim($this->pubdate) != "") {
          $this->ItemPubDate[] = trim($this->pubdate);
          $this->ItemPubDate_t[] = strtotime($this->pubdate);
        }
        else if (trim($this->pubdateDC) != "") {
          $this->ItemPubDate[] = trim($this->pubdateDC);
          $this->ItemPubDate_t[] = getRFDdate($this->pubdateDC);
        } else {
          $this->ItemPubDate[] = date("D, d M Y H:i:s +0000");
          $this->ItemPubDate_t[] = time();
        }
        $this->ItemGuid[] = trim($this->guid);
        $this->ItemEnclosureURL[] = trim($this->enclosureURL);
      }
      $this->title = "";
      $this->description = "";
      $this->contentEncoded = "";
      $this->link = "";
      $this->pubdate = "";
      $this->pubdateDC = "";
      $this->guid = "";
      $this->enclosureURL = "";
      $insideitem = FALSE;
      $level_item = 0;
    }
    elseif (($tagName == "IMAGE") && ($insidechannelimage)) {
      $this->FeedImageURL = trim($this->fimageURL);
      $this->FeedImageTitle = trim($this->fimageTitle);
      $this->FeedImageLink = trim($this->fimageLink);
      $this->fimageURL = "";
      $this->fimageTitle = "";
      $this->fimageLink = "";
      $insidechannelimage = FALSE;
      $level_channelimage = 0;
    }
    elseif ($tagName == "CHANNEL") {
       //
      // Get the pubDate from pubDate first and then dc:date
      if (trim($this->pubdate) != "") {
        $this->FeedPubDate_t = strtotime($this->FeedPubDate);
      }
      else if (trim($this->pubdateDC) != "") {
        $this->FeedPubDate_t = getRFDdate($this->FeedPubDateDC);
      } else {
        $this->FeedPubDate = date("D, d M Y H:i:s +0000");
        $this->FeedPubDate_t = time();
      }
      $insidechannel = FALSE;
      $level_channel = 0;
    }
    elseif ($this->level == $level_channel) {
      if ($tagName == "TITLE") {
        $this->FeedTitle = trim($this->title);
        $this->title = "";
      }
      elseif (($tagName == "DESCRIPTION") || ($tagName == "TAGLINE")) {
        $this->FeedDescription = trim($this->description);
        $this->description = "";
      }
      elseif ($tagName == "CONTENT:ENCODED") {
        $this->FeedContentEncoded = trim($this->contentEncoded);
        $this->contentEncoded = "";
      }
      elseif ($tagName == "LINK") {
        $this->FeedLink = trim($this->link);
        $this->link = "";
      }
    }
  }

  function characterData($parser, $data) {
    GLOBAL $insidechannel;
    GLOBAL $level_channel;
    GLOBAL $insidechannelimage;
    GLOBAL $level_channelimage;
    GLOBAL $insideitem;
    GLOBAL $level_item;

    if (($data == "") || ($data == NULL)) {
    } else {
      if (($insideitem) && ($this->level == $level_item+1)) {
        switch ($this->tag) {
          case "TITLE":
          $this->title .= $data;
          break;

          case "DESCRIPTION":
          $this->description .= $data;
          break;

          case "CONTENT:ENCODED":
          $this->contentEncoded .= $data;
          break;

          case "SUMMARY":
          $this->description .= $data;
          break;

          case "LINK":
          $this->link .= $data;
          break;

          case "PUBDATE":
          $this->pubdate .= $data;
          break;

          case "DC:DATE":
          $this->pubdateDC .= $data;
          break;

          case "MODIFIED":
          $this->pubdateDC .= $data;
          break;

          case "GUID":
          $this->guid .= $data;
          break;
        }
      }
      elseif ($insidechannelimage) {
        switch ($this->tag) {
          case "TITLE":
          $this->fimageTitle .= $data;
          break;

          case "URL":
          $this->fimageURL .= $data;
          break;

          case "LINK":
          $this->fimageLink .= $data;
          break;
        }
      }
      elseif (($insidechannel) && ($this->level == $level_channel+1)) {
        switch ($this->tag) {
          case "TITLE":
          $this->title .= $data;
          break;

          case "DESCRIPTION":
          $this->description .= $data;
          break;

          case "CONTENT:ENCODED":
          $this->contentEncoded .= $data;
          break;

          case "TAGLINE":
          $this->description .= $data;
          break;

          case "LINK":
          $this->link .= $data;
          break;

          case "PUBDATE":
          $this->FeedPubDate .= $data;
          break;

          case "DC:DATE":
          $this->FeedPubDateDC .= $data;
          break;

          case "MODIFIED":
          $this->FeedPubDateDC .= $data;
          break;
        }
      }
    }
  }
}

if (($fd = @fopen($TEMPLATEfilename, "rb")) === FALSE) {
  echo "Unable to open template $TEMPLATEfilename, exiting\n";
  exit -1;
}
$template = NULL;
while (($data = fread($fd, 4096)) != "") {
  $template .= $data;
}
fclose($fd);

if (strstr($template, "~~~NoFutureItems~~~")) {
  $NoFutureItems = TRUE;
}

$xml_parser = xml_parser_create('');
$rss_parser = new RSSParser();
xml_set_object($xml_parser,$rss_parser);
xml_set_element_handler($xml_parser, "startElement", "endElement");
xml_set_character_data_handler($xml_parser, "characterData");
xml_parser_set_option($xml_parser,XML_OPTION_CASE_FOLDING,1);
if (($fd = @fopen($XMLfilename, "rb")) === FALSE) {
  echo "Unable to open RSS Feed $XMLfilename, exiting\n";
  exit -1;
}
// Read the whole file 4k at a time so remote files can be read
while (($XML = fread($fd, 4096)) != "") {
  xml_parse($xml_parser,$XML);
}
fclose($fd);
xml_parser_free($xml_parser);

// make sure the channel contentEncoded is not blank
if ($rss_parser->FeedContentEncoded == "") {
  $rss_parser->FeedContentEncoded = $rss_parser->FeedDescription;
}
$template = str_replace("~~~FeedTitle~~~", $rss_parser->FeedTitle, $template);
$template = str_replace("~~~FeedDescription~~~", $rss_parser->FeedDescription, $template);
$template = str_replace("~~~FeedContentEncoded~~~", $rss_parser->FeedContentEncoded, $template);
$template = str_replace("~~~FeedLink~~~", $rss_parser->FeedLink, $template);
$template = str_replace("~~~FeedPubDate~~~", $rss_parser->FeedPubDate, $template);
$template = str_replace("~~~FeedPubLongDate~~~", date($LongDateFormat, $rss_parser->FeedPubDate_t), $template);
$template = str_replace("~~~FeedPubShortDate~~~", date($ShortDateFormat, $rss_parser->FeedPubDate_t), $template);
$template = str_replace("~~~FeedPubLongTime~~~", date($LongTimeFormat, $rss_parser->FeedPubDate_t), $template);
$template = str_replace("~~~FeedPubShortTime~~~", date($ShortTimeFormat, $rss_parser->FeedPubDate_t), $template);
$template = str_replace("~~~FeedImageUrl~~~", $rss_parser->FeedImageURL, $template);
$template = str_replace("~~~FeedImageTitle~~~", $rss_parser->FeedImageTitle, $template);
$template = str_replace("~~~FeedImageLink~~~", $rss_parser->FeedImageLink, $template);
$pageHeight  = 600;
$resizedPageHeight = 2600+count($rss_parser->ItemTitle)*103;
if($resizedPageHeight > $pageHeight)
	{
	$pageHeight = $resizedPageHeight;
	}


$template = str_replace("~~~PageHeight~~~", $pageHeight, $template);

$match = NULL;

$template = str_replace("~~~NoFutureItems~~~", "", $template);

// Sort by PubDate if requested
if (strstr($template, "~~~SortByPubDate~~~")) {
  $template = str_replace("~~~SortByPubDate~~~", "", $template);

  for ($x = 0; $x < count($rss_parser->ItemTitle)-1; $x++)
  {
    for ($y = $x+1; $y < count($rss_parser->ItemTitle); $y++)
    {
      if ($rss_parser->ItemPubDate_t[$x] < $rss_parser->ItemPubDate_t[$y])
      {
        // Swap them
        $swapTemp = $rss_parser->ItemTitle[$x]; $rss_parser->ItemTitle[$x] = $rss_parser->ItemTitle[$y]; $rss_parser->ItemTitle[$y] = $swapTemp;
        $swapTemp = $rss_parser->ItemDescription[$x]; $rss_parser->ItemDescription[$x] = $rss_parser->ItemDescription[$y]; $rss_parser->ItemDescription[$y] = $swapTemp;
        $swapTemp = $rss_parser->ItemContentEncoded[$x]; $rss_parser->ItemContentEncoded[$x] = $rss_parser->ItemContentEncoded[$y]; $rss_parser->ItemContentEncoded[$y] = $swapTemp;
        $swapTemp = $rss_parser->ItemLink[$x]; $rss_parser->ItemLink[$x] = $rss_parser->ItemLink[$y]; $rss_parser->ItemLink[$y] = $swapTemp;
        $swapTemp = $rss_parser->ItemGuid[$x]; $rss_parser->ItemGuid[$x] = $rss_parser->ItemGuid[$y]; $rss_parser->ItemGuid[$y] = $swapTemp;
        $swapTemp = $rss_parser->ItemPubDate[$x]; $rss_parser->ItemPubDate[$x] = $rss_parser->ItemPubDate[$y]; $rss_parser->ItemPubDate[$y] = $swapTemp;
        $swapTemp = $rss_parser->ItemPubDate_t[$x]; $rss_parser->ItemPubDate_t[$x] = $rss_parser->ItemPubDate_t[$y]; $rss_parser->ItemPubDate_t[$y] = $swapTemp;
        $swapTemp = $rss_parser->ItemEnclosureURL[$x]; $rss_parser->ItemEnclosureURL[$x] = $rss_parser->ItemEnclosureURL[$y]; $rss_parser->ItemEnclosureURL[$y] = $swapTemp;
      }
    }
  }
}

// The the maximum items requested
if (strstr($template, "~~~FeedMaxItems=")) {
  // Limit the maximun number of items displayed
  if (preg_match("/~~~FeedMaxItems=([0-9-]*)~~~/", $template, $match) !== FALSE) {
    if (($match[0] != "") && ($match[1] != "")) {
      $FeedMaxItems = $match[1];
      $template = str_replace("~~~FeedMaxItems=$match[1]~~~", "", $template);
      if (abs($FeedMaxItems) > count($rss_parser->ItemTitle)) {
        if ($FeedMaxItems > 0) {
          $FeedMaxItems = count($rss_parser->ItemTitle);
        } else {
          $FeedMaxItems = -count($rss_parser->ItemTitle);
        }
      }
    }
  }
}

//
// Find the string, if it exists, between the ~~~EndItemsRecord~~~ and ~~~BeginItemsRecord~~~
//
while ((strstr($template, "~~~BeginItemsRecord~~~")) !== FALSE) {
  $match = NULL;
  $allitems = NULL;
  $loop_limit = min(abs($FeedMaxItems), count($rss_parser->ItemTitle));
  if (($parts = split("~~~BeginItemsRecord~~~", $template)) !== FALSE) {
    if (($parts = split("~~~EndItemsRecord~~~", $parts[1])) !== FALSE) {
      $WholeBlock = $parts[0];
      //
      // Check for ~~~BeginAlternateItemsRecord~~~
      //
      if (strstr($WholeBlock, "~~~BeginAlternateItemsRecord~~~")) {
        $parts = split("~~~BeginAlternateItemsRecord~~~", $WholeBlock);
        $block1 = $parts[0];
        $block2 = $parts[1];
      } else {
        $block1 = $WholeBlock;
        $block2 = $WholeBlock;
      }
      if ($FeedMaxItems < 0) {
        for ($x = count($rss_parser->ItemTitle)-1; $x >= count($rss_parser->ItemTitle) + $FeedMaxItems; $x--) {
          $item = str_replace("~~~ItemTitle~~~", $rss_parser->ItemTitle[$x], $block1);
          $item = str_replace("~~~ItemDescription~~~", $rss_parser->ItemDescription[$x], $item);
          $item = str_replace("~~~ItemContentEncoded~~~", $rss_parser->ItemContentEncoded[$x], $item);
          $item = str_replace("~~~ItemLink~~~", $rss_parser->ItemLink[$x], $item);
          $item = str_replace("~~~ItemPubDate~~~", $rss_parser->ItemPubDate[$x], $item);
          $item = str_replace("~~~ItemGuid~~~", urlencode($rss_parser->ItemGuid[$x]), $item);
          $item = str_replace("~~~ItemPubLongDate~~~", date($LongDateFormat, $rss_parser->ItemPubDate_t[$x]), $item);
          $item = str_replace("~~~ItemPubShortDate~~~", date($ShortDateFormat, $rss_parser->ItemPubDate_t[$x]), $item);
          $item = str_replace("~~~ItemPubLongTime~~~", date($LongTimeFormat, $rss_parser->ItemPubDate_t[$x]), $item);
          $item = str_replace("~~~ItemPubShortTime~~~", date($ShortTimeFormat, $rss_parser->ItemPubDate_t[$x]), $item);
          $item = str_replace("~~~ItemEnclosureUrl~~~", $rss_parser->ItemEnclosureURL[$x], $item);
          $allitems .= $item;
          $x--;
          if ($x >= count($rss_parser->ItemTitle) + $FeedMaxItems) {
            //
            // This is at least one more item so use the Alternate definition
            //
            $item = str_replace("~~~ItemTitle~~~", $rss_parser->ItemTitle[$x], $block2);
            $item = str_replace("~~~ItemDescription~~~", $rss_parser->ItemDescription[$x], $item);
            $item = str_replace("~~~ItemContentEncoded~~~", $rss_parser->ItemContentEncoded[$x], $item);
            $item = str_replace("~~~ItemLink~~~", $rss_parser->ItemLink[$x], $item);
            $item = str_replace("~~~ItemPubDate~~~", $rss_parser->ItemPubDate[$x], $item);
            $item = str_replace("~~~ItemGuid~~~", urlencode($rss_parser->ItemGuid[$x]), $item);
            $item = str_replace("~~~ItemPubLongDate~~~", date($LongDateFormat, $rss_parser->ItemPubDate_t[$x]), $item);
            $item = str_replace("~~~ItemPubShortDate~~~", date($ShortDateFormat, $rss_parser->ItemPubDate_t[$x]), $item);
            $item = str_replace("~~~ItemPubLongTime~~~", date($LongTimeFormat, $rss_parser->ItemPubDate_t[$x]), $item);
            $item = str_replace("~~~ItemPubShortTime~~~", date($ShortTimeFormat, $rss_parser->ItemPubDate_t[$x]), $item);
            $item = str_replace("~~~ItemEnclosureUrl~~~", $rss_parser->ItemEnclosureURL[$x], $item);
            $allitems .= $item;
          }
        }
      } else {
        for ($x = 0; $x < $loop_limit; $x++) {
          $item = str_replace("~~~ItemTitle~~~", $rss_parser->ItemTitle[$x], $block1);
          $item = str_replace("~~~ItemDescription~~~", $rss_parser->ItemDescription[$x], $item);
          $item = str_replace("~~~ItemContentEncoded~~~", $rss_parser->ItemContentEncoded[$x], $item);
          $item = str_replace("~~~ItemLink~~~", $rss_parser->ItemLink[$x], $item);
          $item = str_replace("~~~ItemPubDate~~~", $rss_parser->ItemPubDate[$x], $item);
          $item = str_replace("~~~ItemGuid~~~", urlencode($rss_parser->ItemGuid[$x]), $item);
          $item = str_replace("~~~ItemPubLongDate~~~", date($LongDateFormat, $rss_parser->ItemPubDate_t[$x]), $item);
          $item = str_replace("~~~ItemPubShortDate~~~", date($ShortDateFormat, $rss_parser->ItemPubDate_t[$x]), $item);
          $item = str_replace("~~~ItemPubLongTime~~~", date($LongTimeFormat, $rss_parser->ItemPubDate_t[$x]), $item);
          $item = str_replace("~~~ItemPubShortTime~~~", date($ShortTimeFormat, $rss_parser->ItemPubDate_t[$x]), $item);
          $item = str_replace("~~~ItemEnclosureUrl~~~", $rss_parser->ItemEnclosureURL[$x], $item);
          $allitems .= $item;
          $x++;
          if ($x < $loop_limit) {
            //
            // This is at least one more item so use the Alternate definition
            //
            $item = str_replace("~~~ItemTitle~~~", $rss_parser->ItemTitle[$x], $block2);
            $item = str_replace("~~~ItemDescription~~~", $rss_parser->ItemDescription[$x], $item);
            $item = str_replace("~~~ItemContentEncoded~~~", $rss_parser->ItemContentEncoded[$x], $item);
            $item = str_replace("~~~ItemLink~~~", $rss_parser->ItemLink[$x], $item);
            $item = str_replace("~~~ItemPubDate~~~", $rss_parser->ItemPubDate[$x], $item);
            $item = str_replace("~~~ItemGuid~~~", urlencode($rss_parser->ItemGuid[$x]), $item);
            $item = str_replace("~~~ItemPubLongDate~~~", date($LongDateFormat, $rss_parser->ItemPubDate_t[$x]), $item);
            $item = str_replace("~~~ItemPubShortDate~~~", date($ShortDateFormat, $rss_parser->ItemPubDate_t[$x]), $item);
            $item = str_replace("~~~ItemPubLongTime~~~", date($LongTimeFormat, $rss_parser->ItemPubDate_t[$x]), $item);
            $item = str_replace("~~~ItemPubShortTime~~~", date($ShortTimeFormat, $rss_parser->ItemPubDate_t[$x]), $item);
            $item = str_replace("~~~ItemEnclosureUrl~~~", $rss_parser->ItemEnclosureURL[$x], $item);
            $allitems .= $item;
          }
        }
      }
      $template = str_replace("~~~BeginItemsRecord~~~".$WholeBlock."~~~EndItemsRecord~~~", $allitems, $template);
    }
  }
}

echo $template;

?>
