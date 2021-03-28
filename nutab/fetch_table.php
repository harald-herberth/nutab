<?php
/*
Copyright 2018 Harald Herberth

Licence BSD
Darf frei verwendet werden, solange damit keine Werbung in die erzeugten Tabellen eingefügt wird

Liest Tabellen und Spielpläne aus nuLiga aus, und bringt sie in einem JSON Format zurück
Zu verwenden in tabellen.js


Parameter
url: zeigt direkt auf eine NuLiga URL für eine aktuelle Tabelle oder Spielplan
spielplan: true sagt: spielplan laden, nicht aktuell
spielplanverein: true sagt: gesamt-spielplan laden, nicht aktuell
	alle: alle Spiele anzeigen
	von, bis: nur Spiele in diesem Zeitraum anzeigen
aktuell gibt es in nuLiga nicht mehr, ist aus ISS3 übriggeblieben, entfernen
cty: content type (falls der Aufrufer was ganz spezielles braucht)
jh: JSON header content
auchak: in die Tabellen ist auch AK Mannschaften normal drin, und nicht am Ende mit AK

Aufruf erfolgt über JSONP oder XHR und CORS

*/
error_reporting(E_ALL & ~(E_NOTICE|E_WARNING|E_DEPRECATED));
//error_reporting(E_ALL & ~(E_NOTICE|E_DEPRECATED));
@ini_set("display_errors", "1");

// zum testen kann der Cache auch mal disabled werden. 
// Man sollte aber immer mit arbeiten, weil das die Aufbauzeiten der eigenen Seiten verbessert, und die Last auf die nu Server senkt.
define("DISABLE_CACHE", 0); 
define("DEBUG_GET", 0); 

// alle Parameter als global einlesen
// auch dies kann man mal besser machen, wenn man will
if (1 and !ini_get('register_globals')) {
	$superglobals = array(
		$_SERVER, 
		$_COOKIE, 
		//$_ENV,
		//$_FILES, 
		$_POST, 
		$_GET);
	if (0 and isset($_SESSION)) {
		array_unshift($superglobals, $_SESSION);
	}
	foreach ($superglobals as $superglobal) {
		extract($superglobal, EXTR_SKIP);
	}
	unset($superglobal);
	unset($superglobals);
}
@ini_set("default_charset","ISO8859-1");
@ini_set("date.timezone", "Europe/Berlin");
// damit preg_match_all auch für lange strings geht
@ini_set("pcre.backtrack_limit","8000000");

ob_start(); // damit warnings nicht in das XML kommen

$q = $_SERVER['QUERY_STRING'];
$q = preg_replace("/callback=.*?&/", "", $q);
$q = preg_replace("/_=.*?(&|$)/", "", $q);
//$ref = $_SERVER['HTTP_REFERER'];
$callback = preg_replace('/[^a-zA-Z0-9$_.]/', "", array_key_exists("callback", $_GET) ? $_GET["callback"] : "");
$u = "";
$r = "";
$auchak = (int) $auchak;
$auchak = 0;

// debug ausgabe
if (!function_exists("pp")) {
function pp($v) {
	$x = htmlspecialchars(print_r($v,true)); $x = str_replace("[","<br>[",$x); echo$x . "<br><br>";
}
}
$debug_message = "";
function dd($x) {
	global $debug_message;
	$debug_message .= htmlentities($x) . " ";
}

function ex($m) {
	echo "<error>";
	//echo htmlentities($m);
	echo print_r($m, true);
	echo "</error>";
}
require_once("nutab.php");
function write_log($f) {
	$fp = fopen("aaaa.txt", "ab"); fwrite($fp, $f."\r"); fclose($fp);
}

/*
 * Anfragen mit dem gleichen Inhalt werden gecached
 * damit nicht jeder Seiten-Refresh zu einem Roundtrip bis zum nuLiga Server führt
 */
function clear_cache() {
	$ff = "cache/clear_cache.txt";
	if (file_exists($ff) && time() < filemtime($ff) + 300) return 0;
	$ret = @touch($ff);
	if ($ret === false) return -1;
	$d = @opendir("cache");
	if ($d === false) return -2;
	$tt = time();
        while (($f = readdir($d)) !== false) {
		if ($f == "." || $f == ".." || $f == "nu_state.txt" || $f == "clear_cache.txt") continue;
		$f = "cache/" . $f;
		if (is_file($f)) {
			if (filemtime($f) + 600 < $tt) {
				@unlink($f);
				//write_log($f);
			}
		}
	}	
	closedir($d);
	return 1;
}

function get_cache($u) {
	if (DISABLE_CACHE) return 0;
	$ret = clear_cache();
	if ($ret < 0) return $ret;
	$r = "";
	preg_match("§.*\?(.*)§is", $u, $u); $u = $u[1];
	$u = "cache/" . strtolower(preg_replace("§[^A-Za-z0-9]§", "_", $u)) . ".txt";
	if (file_exists($u) and time() < filemtime($u) + 10*60) {
		$r = file_get_contents($u);
	}
	return $r;
}

function put_cache($u, $r) {
	if (DISABLE_CACHE) return;
	preg_match("§.*\?(.*)§is", $u, $u); $u = $u[1];
	$u = "cache/" . strtolower(preg_replace("§[^A-Za-z0-9]§", "_", $u)) . ".txt";
	$fp = fopen($u, "wb"); 
	if ($fp === false) return;
	fwrite($fp, $r); fclose($fp);
	return;
}


// hält die Verbindung mit nuliga, kann Seiten abrufen, und POSTs absenden
// umgestellt auf requests for php nehmen https://requests.ryanmccue.info/
require_once("Requests-1.7.0/library/Requests.php");
Requests::register_autoloader();
class NuLiga {
	var $cookies;
	function init($state) {
		if ($state) $this->cookies = unserialize(base64_decode($state));
		else $this->cookies = Array();
	}
	function get_state() {
		return base64_encode(serialize($this->cookies));
	}
	function login($bezirk) {
		// braucht man gar nicht
		die("todo");
	}
	function get($url, $referer = "") {
		$DEB = DEBUG_GET;
		// url muss absolut sein
		if (!$url) throw new Exception("mit leerer URL aufgerufen");
		if (!preg_match(';^http;i', $url)) throw new Exception("url nicht absolut: $url");
		if (!$this->cookies) $this->cookies = array();
		$o = array(
			//"transport" => "Requests_Transport_cURL",
			//"cookies" => $this->cookies,
			//"proxy" => "localhost:8888",
			"verify" => false, // true wäre besser, aber uns ist es egal mit welchem Server wir reden
			"timeout" => 2*60,
			"follow_redirects" => "false", 
			"useragent" => "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0)"
			);
		if (0) $o = array_merge($o, 
			array("proxy" => "localhost:8888", "verify" => false));
		$h = array(
			"Referer" => $referer ? $referer : $url,
			"Connection" => "close", 
			"Accept-Language" => "de",
		);
		if ($DEB) {
			$t1 = microtime(true); $t1s = date("H:i:s", (int)$t1); $t1m = $t1*1000%1000;
			$handle = fopen("helper.log", 'a'); fwrite($handle, "$t1s.$t1m Get $url\r\n"); fclose($handle);
		}
		$r = Requests::get($url, $h, $o);
		$this->cookies = $r->cookies;
		$this->ref = $url;
		$ret = rutf($r->body);
		if ($DEB) {
			$t1 = microtime(true); $t1s = date("H:i:s", (int)$t1); $t1m = $t1*1000%1000;
			$handle = fopen("helper.log", 'a'); fwrite($handle, "$t1s.$t1m Got $url\r\n"); fclose($handle);
			$handle = fopen("helper.log", 'a'); fwrite($handle, "$ret\r\n"); fclose($handle);
		}
		return $ret;
	}
}

// replace utf encoding to regular "ascii"
function rutf($s) {
	$s = utf8_decode($s);
	$s = str_replace('&nbsp;', ' ', $s);
	$s = html_entity_decode($s);
	$s = preg_replace_callback('~&#x([0-9a-f]+);~i', function($m) {$x = $m[0]; return "chr(hexdec($x))";}, $s);
	$s = preg_replace_callback('~&#([0-9]+);~', function($m) {$x = $m[0]; return "chr($x)";}, $s);
	return $s;
}

// url zu nuliga kann direkt angegenen sein
$team = "";
if ($url) {
	$url = str_replace("http:", "https:", $url);
	if (preg_match(';^https://(.*?)\.liga\.nu/.*?/nuLiga(.*?)\.woa.*?groupPage\?(.*);i', $url, $x)) {
		// Tabelle/Spielplan für dieses Gruppe
		$verband = $x[1];
		$sportart = $x[2];
		$qs = $x[3]."&";
		if (preg_match(';championship=(.*?)&;i', $qs, $x)) $cs = urldecode($x[1]);
		if (preg_match(';group=(.*?)&;i', $qs, $x)) $gruppe = "&group=".$x[1];
		$url = "https://$verband.liga.nu/cgi-bin/WebObjects/nuLiga{$sportart}.woa/wa/groupPage?championship=".urlencode($cs)."$gruppe";
		if ($auchak) $url .= "&displayTyp=gesamt&displayDetail=tableWithIgnoredTeams";
		//ex($url);die;
	} else if (preg_match(';^https://(.*?)\.liga\.nu/.*?/nuLiga(.*?)\.woa.*?teamPortrait\?(.*);i', $url, $x)) {
		// Tabelle/Spielplan für dieses Team
		$verband = $x[1];
		$sportart = $x[2];
		$qs = $x[3]."&";
		if (preg_match(';team=(.*?)&;i', $qs, $x)) $team = "team=".$x[1];
		if (preg_match(';teamtable=(.*?)&;i', $qs, $x)) $team = "teamtable=".$x[1];
		if (preg_match(';championship=(.*?)&;i', $qs, $x)) $cs = urldecode($x[1]);
		if (preg_match(';group=(.*?)&;i', $qs, $x)) $gruppe = "&group=".$x[1];
		$url = "https://$verband.liga.nu/cgi-bin/WebObjects/nuLiga{$sportart}.woa/wa/teamPortrait?{$team}&championship=".urlencode($cs)."$gruppe";
		$nurteam = 1;
		if (!$spielplan) $error = "Tabelle geht nicht mit URL auf Team";
	} else {
		//print_r("else ".$url);die;
		$url = "";
	}
	$u = $url;
	$spielplanverein = 0;
}


if (!$verband) $verband = "bhv-handball";
if (!$sportart) $sportart = "HBDE";
if ($spielplanverein) {
	$spielplan = 0;
	$club = (int) $club;
	if ($alle) {
		// automatisch auf aktuelle Saison
		$jahr = date("Y", time() + 184*24*3600);
		$e = "30.06.$jahr";
		$jahr--;
		$s = "01.07.$jahr";
	} else {
		$s = $e = "";
		if (preg_match(";^\d\d\.\d\d\.\d\d\d\d$;is", $von)) $s = $von;
		if (preg_match(";^\d\d\.\d\d\.\d\d\d\d$;is", $bis)) $e = $bis;
		if (!$s and !preg_match(";^\d+$;is", $von)) $von = 14;
		if (!$e and !preg_match(";^\d+$;is", $bis)) $bis = 14;
		if (!$s) $s = date("d.m.Y", time()-$von*24*60*60);
		if (!$e) $e = date("d.m.Y", time()+$bis*24*60*60);
	}
	$ss = $s; $ee = $e;
	if ($s and $e) $s = "&searchTimeRangeFrom=$s&searchTimeRangeTo=$e"; else $s = "";
	$u = "https://$verband.liga.nu/cgi-bin/WebObjects/nuLiga{$sportart}.woa/wa/clubMeetings?searchTimeRange=2&searchType=1{$s}&club={$club}&searchMeetings=Suchen";
	//die($u);
}
else $club = "";

if ($u && ($gruppe || $club || $nurteam)) {
	if ($spielplan) {
		if (!$team) $u = "https://$verband.liga.nu/cgi-bin/WebObjects/nuLiga{$sportart}.woa/wa/groupPage?displayTyp=gesamt&displayDetail=meetings&championship=".urlencode($cs)."$gruppe";
		if ($aktuell) $u .= "&aktuell=1";
	}
	//dd($u);
	// von nuliga lesen
	$r = get_cache($u);
	if (is_int($r) && $r < 0) {
		$r = "<error>Problem auf eigenem Server. Verzeichnis cache/ existiert nicht oder ist nicht schreibbar. Fehlercode $r</error>";
		goto cache_err;
	}
	if (!$r) {
		if ($spielplan) {
			$tab = new NuPlan2;
			$tab->set_base($u);
			if ($aktuell) $tab->set_aktuell(true);
		} else {
			if ($spielplanverein) {
				$tab = new NuPlan;
				$tab->set_base($u);
			} else {
				$tab = new NuTab;
				$tab->set_base($u);
			}
		}
		$nu = new NuLiga;
		$r = $nu->get($u, $u);
		$tab->init($r);
		$r = $tab->get_xml();
		put_cache($u, $r);
	}

	if ($spielplan and ($von or $bis)) {
		// von und bis als Datum zurück, in JS filtern wir dann
		$s = $e = "";
		if (preg_match(";^\d\d\.\d\d\.\d\d\d\d$;is", $von)) 
			$s = substr($von,6,4).".".substr($von,3,2).".".substr($von,0,2);
		if (preg_match(";^\d\d\.\d\d\.\d\d\d\d$;is", $bis))
			$e = substr($bis,6,4).".".substr($bis,3,2).".".substr($bis,0,2);
		if (!$s and !preg_match(";^\d+$;is", $von)) $von = 14;
		if (!$e and !preg_match(";^\d+$;is", $bis)) $bis = 14;
		if (!$s) $s = date("Y.m.d", time()-$von*24*60*60);
		if (!$e) $e = date("Y.m.d", time()+$bis*24*60*60);
		if (preg_match(';^<Spielplan>;', $r)) {
			$r = preg_replace(';^<Spielplan>;', "<Spielplan>\n<von>$s</von><bis>$e</bis>", $r);
		}
	}
	cache_err:
	//pp($r);die;
	//$r = file_get_contents($u);
	//if (!$utf) $r = utf8_decode($r);
	//print_r(htmlentities($r)); die;
}

$ob = ob_get_contents(); ob_end_clean();
if ($ob) {
	$ob = preg_replace(';<br />;ismU', '', $ob);
	$ob = preg_replace(";\n\n;ismU", "\n", $ob);
	$ob = "<pre>$ob</pre>";
	$ob = htmlentities($ob);
	$r = "<error>Serverfehler in fetch_table.php $ob</error>";
}

if ($error) $r = "<error>$error</error>";
else if (!$r and $spielplanverein) $r = "<error>Keine Spiele im Zeitraum gefunden ($ss-$ee)</error>";
else if (!$r) $r = "<error>Konnte Tabelle/Spielplan bei NuLiga nicht finden</error>";
if (!$u) $r = "<error>Keine gültige URL angegeben</error>";

// nach JSON Konvertieren und zurückliefern
//error_reporting(E_ALL & ~(E_NOTICE|E_WARNING));
if ($jn) {
	try {
	$r = new SimpleXMLElement($r);
	if ($debug_message) $r->addChild("debug", $debug_message);
	$r = json_encode($r);
	// empty objects to empty strings {} => ""
	$r = str_replace("{}", '""', $r);
	} catch (Exception $e) {
		$r = '{"error": "XML Parser Exception"}';
	}
} else {
	try {
	$r = "<wrapper>$r</wrapper>"; // topmost node geht verloren, für $jn pfad ändern wir das nicht, wegen kompatibel
	$r = new SimpleXMLElement($r);
	if ($debug_message) $r->addChild("debug", $debug_message);
	$r = json_encode($r);
	// empty objects to empty strings {} => ""
	$r = str_replace("{}", '""', $r);
	} catch (Exception $e) {
		$r = '{"error": "XML Parser Exception"}';
	}
}
//print_r($r); die;

if (!$r or $r == "[]") {
	$r = '{"error": "Keine gueltigen Daten unter dieser Adresse"}';
	//@unlink("cache/nu_state.txt");
}
// set content type
if (!$callback) $jh = 1;
if ($cty) header("Content-Type: $cty");
else if ($jh) header("Content-Type: application/json; charset=utf-8");
else header("Content-Type: application/javascript; charset=utf-8");
// CORS
if ($_SERVER['HTTP_ORIGIN']) header("Access-Control-Allow-Origin: *");

if ($callback) echo "$callback($r)";
else echo "$r";
?>
