<?php

class SearchTrezzorTracker
{
  private $baseUrl = 'http://tracker.czech-server.com';
  private $searchPrefix = '/torrents.php?search=%s&category=0&active=1';
  private $loginUrl = '/prihlasenie.php';
  private $COOKIE = '/tmp/trezzortracker.cookie';
  private $categories;

  const SLASH = '/';

  public function __construct()
  {
    $this->searchPrefix = $this->baseUrl . $this->searchPrefix;
    $this->loginUrl = $this->baseUrl . $this->loginUrl;
    $this->categories = array(
      1 => 'DVD CZ/SK dabing',
      2 => 'DVD CZ/SK titulky',
      3 => 'DVD Hudebni video',
      4 => 'XviD, DivX CZ/SK dabing',
      36 => 'XviD, DivX CZ/SK titulky',
      5 => 'TV-rip CZ/SK dabing',
      41 => 'HD Seriály CZ/SK dabing',
      42 => 'HD Seriály CZ/SK titulky',
      7 => 'Seriály CZ/SK dabing',
      37 => 'Seriály CZ/SK titulky',
      9 => 'XXX CZ/SK dabing',
      32 => 'XXX HD CZ/SK dabing',
      31 => 'HDTV CZ/SK Dabing',
      33 => 'HDTV CZ/SK Titulky',
      39 => '3D HDTV CZ/SK Dabing',
      40 => '3D HDTV CZ/SK Titulky',
      35 => 'HDTV Hudebni video',
      13 => 'Hudba CZ/SK scéna',
      24 => 'Mluv. slovo CZ/SK dabing',
      10 => 'DTS audio',
      14 => 'Hry',
      17 => 'Programy',
      15 => 'Cestiny,patche,upgrady',
      18 => 'Knihy CZ/SK lokalizace',
      19 => 'Komiks CZ/SK lokalizace',
      16 => 'Foto,obrázky',
      20 => 'Konzole',
      21 => 'Mobilmánia',
      22 => 'Ostatní CZ/SK scéna',
      23 => 'Na prani non CZ/SK',
      27 => 'TreZzoR rls',
    );
  }

  public function prepare($curl, $query, $username, $password)
  {
    $url = $this->searchPrefix;
    if ($query == 'FEED') {
      curl_setopt($curl, CURLOPT_URL, sprintf($url, ''));
    } else {
      curl_setopt($curl, CURLOPT_URL, sprintf($url, urlencode(iconv("UTF-8", "Windows-1250", $query))));
    }

    curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);

    if ($username !== NULL && $password !== NULL) {
      $this->VerifyAccount($username, $password);
      curl_setopt($curl, CURLOPT_COOKIEFILE, $this->COOKIE);
    }

  }

  public function GetCookie()
  {
    return $this->COOKIE;
  }

  public function VerifyAccount($username, $password)
  {
    $ret = FALSE;

    if (file_exists($this->COOKIE)) {
      unlink($this->COOKIE);
    }

    $PostData = array('uid' => $username, 'pwd' => $password);
    $PostData = http_build_query($PostData);

    $fscurl = curl_init();
    $headers = array
    (
      'Accept: 	text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
      'Accept-Language: cs,en-us;q=0.7,en;q=0.3',
      'Accept-Encoding: deflate',
      'Accept-Charset: windows-1251,utf-8;q=0.7,*;q=0.7'
    );
    curl_setopt($fscurl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($fscurl, CURLOPT_URL, $this->loginUrl);
    curl_setopt($fscurl, CURLOPT_FAILONERROR, 1);
    curl_setopt($fscurl, CURLOPT_REFERER, $this->loginUrl);
    curl_setopt($fscurl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($fscurl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($fscurl, CURLOPT_TIMEOUT, 20);
    curl_setopt($fscurl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
    curl_setopt($fscurl, CURLOPT_POST, 1);
    curl_setopt($fscurl, CURLOPT_COOKIEJAR, $this->COOKIE);
    curl_setopt($fscurl, CURLOPT_COOKIEFILE, $this->COOKIE);
    curl_setopt($fscurl, CURLOPT_POSTFIELDS, $PostData);

    $Result = curl_exec($fscurl);
    curl_close($fscurl);

    if (FALSE !== strpos($Result, 'If your browser')) {
      $ret = TRUE;
    }
    return $ret;
  }


  public function parse($plugin, $response)
  {
    date_default_timezone_set('Europe/Bratislava');

    $cut_start = stripos($response, "id=\"torrenty_tabulka\"");
    $response = substr($response, $cut_start);

    $response = preg_replace('/(\s+)/is', ' ', $response);

    $singleTorrent = "<tr class=\"torrenty_lista\">(?<entry>.*)<\/tr>";

    $regcolumns = "<td.*>(?<singleData>.*)<\/td>";

    $pageTitleRegex = "<a.*href=\"(?<url>details\.php.*)\".*>(?<title>.*)<\/a";
    $categoryRegex = "torrents\.php\?onlycat=(?<categoryId>[0-9]+)>";
    $downloadRegex = "href=(download.php?.*\.torrent)";
    $sizeRegex = "(?<sizeNumeric>[0-9\,\.]+) (?<sizeUnit>GB|MB|KB)";
    $seedsRegex = "<a.*href=\".*\".*>(.*)<\/a>";
    $leachsRegex = "<a.*href=\".*\".*>(.*)<\/a>";
    $datetimeRegex = "([0-9]{1,2}\/[0-9]{1,2}\/[0-9]{4} ([0-9]|0[0-9]|1[0-9]|2[0-3]):[0-5][0-9])";

    $res = 0;
    if (preg_match_all("/$singleTorrent/siU", $response, $matches2)) {

      foreach ($matches2['entry'] as $match2) {
        $title = "Unknown title";
        $download = "Unknown download link";
        $size = 0;
        $datetime = "1970-00-00";
        $page = "Default page";
        $hash = '';
        $seeds = 0;
        $leechs = 0;
        $category = "Unknown category";

        if (preg_match_all("/$regcolumns/siU", $match2, $matches1)) {
          // $title = $matches1['singleData'][1];

          foreach ($matches1['singleData'] as $key => $singleData) {
            switch ($key) {
              case 0:
                if (preg_match("/$categoryRegex/siU", $singleData, $matches)) {
                  $categoryId = intval($matches['categoryId']);
                  $category = isset($this->categories[$categoryId]) ? $this->categories[$categoryId] : 'nezaradena kategoria: ' . $categoryId;
                }
                break;
              case 1:
                if (preg_match("/$pageTitleRegex/siU", $singleData, $matches)) {
                  $title = $matches['title'];
                  $page = $this->baseUrl . self::slash . $matches['url'];
                  $hash = md5($res . $title);
                }
                break;
              case 2:
                // contains only comment number
                break;
              case 3:
                if (preg_match("/$downloadRegex/siU", $singleData, $matches)) {
                  $download = $this->baseUrl . self::SLASH . $matches[1];
                }
                break;
              case 5:
                if (preg_match("/$datetimeRegex/siU", $singleData, $matches)) {
                  $dateTokens = explode('/', $matches[1]);
                  $datetime = $dateTokens[2] . "-" . $dateTokens[1] . "-" . $dateTokens[0];
                }
                break;
              case 6:
                if (preg_match("/$sizeRegex/siU", $singleData, $matches)) {
                  $size = str_replace(",", ".", $matches['sizeNumeric']);
                  switch (trim($matches['sizeUnit'])) {
                    case 'KB':
                      $size = $size * 1024;
                      break;
                    case 'MB':
                      $size = $size * 1024 * 1024;
                      break;
                    case 'GB':
                      $size = $size * 1024 * 1024 * 1024;
                      break;
                  }
                  $size = floor($size);
                }
                break;
              case 7:
                if (preg_match("/$seedsRegex/siU", $singleData, $matches)) {
                  $seeds = $matches[1];
                }
                break;
              case 8:
                if (preg_match("/$leachsRegex/siU", $singleData, $matches)) {
                  $leechs = $matches[1];
                }
                break;
            }
          }
        }

        $plugin->addResult($title, $download, $size, $datetime, $page, $hash, $seeds, $leechs, $category);
        $res++;
      }
    }

    return $res;
  }

}

?>
