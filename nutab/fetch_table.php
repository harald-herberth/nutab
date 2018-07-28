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
verein:
cty: content type (falls der Aufrufer was ganz spezielles braucht)
jh: JSON header content

Aufruf erfolgt über JSONP

Todo:
Umstellen von JSONP auf XHR (dann aber mit CORS, damit andere Vereine das auch nutzen koennen)
Umstellen von Badgerfish auf normals JSON (das ist auch noch aus ISS3 Zeiten da)

*/

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
// damit preg_match_all auch für lange strings geht
ini_set("pcre.backtrack_limit","2000000");

$q = $_SERVER['QUERY_STRING'];
$q = preg_replace("/callback=.*?&/", "", $q);
$q = preg_replace("/_=.*?(&|$)/", "", $q);
//$ref = $_SERVER['HTTP_REFERER'];
$callback = preg_replace('/[^a-zA-Z0-9$_.]/', "", $_GET["callback"]);
$u = "";
$r = "";
$verein = utf8_decode($verein);
$verein = str_replace("?", "_", $verein);

// debug ausgabe
if (!function_exists("pp")) {
function pp($v) {
	$x = htmlspecialchars(print_r($v,true)); $x = str_replace("[","<br>[",$x); echo$x . "<br><br>";
}
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
	if (file_exists($ff) && time() < filemtime($ff) + 300) return;
	touch($ff);
	$d = @opendir("cache");
	if (!$d) return;
	$tt = time();
        while (($f = @readdir($d)) !== false) {
		if ($f == "." || $f == ".." || $f == "nu_state.txt"|| $f == "clear_cache.txt") continue;
		$f = "cache/" . $f;
		if (is_file($f)) {
			if (@filemtime($f) + 600 < $tt) {
				@unlink($f);
				//write_log($f);
			}
		}
	}	
	closedir($d);
}

function get_cache($u) {
	clear_cache();
	$r = 0;
	//return $r;
	preg_match("§.*\?(.*)§is", $u, $u); $u = $u[1];
	$u = "cache/" . strtolower(preg_replace("§[^A-Za-z0-9]§", "_", $u)) . ".txt";
	if (file_exists($u) and time() < filemtime($u) + 10*60) {
		$r = file_get_contents($u);
	}
	return $r;
}

function put_cache($u, $r) {
	//return;
	preg_match("§.*\?(.*)§is", $u, $u); $u = $u[1];
	$u = "cache/" . strtolower(preg_replace("§[^A-Za-z0-9]§", "_", $u)) . ".txt";
	$fp = fopen($u, "wb"); fwrite($fp, $r); fclose($fp);
	return;
}


// hält die Verbindung mit nuliga, kann Seiten abrufen, und POSTs absenden
// heute würde ich eher requests for php nehmen https://requests.ryanmccue.info/
require_once("Snoopy.class.php");
class NuLiga {
	var $snoopy;
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
		$DEB = 0;
		$s = new Snoopy();
		$s->agent = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)";
		$s->rawheaders = Array("Connection" => "close", "Accept-Language" => "de");
		if ($referer) $s->rawheaders['Referer'] = $referer;
		$s->maxredirs = 0;
		$s->cookies = $this->cookies;
		if ($DEB) {
			$t1 = microtime(true); $t1s = date("H:i:s", (int)$t1); $t1m = $t1*1000%1000;
			//$handle = fopen("helper.log", 'a'); fwrite($handle, "$t1s.$t1m Get $url\r\n"); fclose($handle);
		}
		$s->fetch($url);
		while (preg_match("/Error 503\./", $s->results)) {
			if ($DEB) {
				$t1 = microtime(true); $t1s = date("H:i:s", (int)$t1); $t1m = $t1*1000%1000;
				//$handle = fopen("helper.log", 'a'); fwrite($handle, "$t1s.$t1m 503 $url\r\n"); fclose($handle);
			}
			$s->fetch($url);
		}
		if ($DEB) {
			$t1 = microtime(true); $t1s = date("H:i:s", (int)$t1); $t1m = $t1*1000%1000;
			//$handle = fopen("helper.log", 'a'); fwrite($handle, "$t1s.$t1m Got $url\r\n"); fclose($handle);
		}
		//if (!($s->results > "")) return $s->error;
		$s->setcookies();
		$this->cookies = $s->cookies;
		$this->ref = $url;
		$r = rutf($s->results);
		//$r = $s->results;
		return $r;
	}
}
// replace utf encoding to regular "ascii"
function rutf($s) {
	$s = utf8_decode($s);
	$s = str_replace('&nbsp;', ' ', $s);
	$s = html_entity_decode($s);
	$s = preg_replace('~&#x([0-9a-f]+);~ei', 'chr(hexdec("\\1"))', $s);
	$s = preg_replace('~&#([0-9]+);~e', 'chr(\\1)', $s);
	return $s;
}

// url zu nuliga kann direkt angegenen sein
if ($url) {
	$url = str_replace("https:", "http:", $url);
	if (preg_match(';^http://(.*?)\.liga\.nu/.*?championship=(.*?)&group=(\d+);i', $url, $x)) {
		// Tabelle für diese Gruppe
		$verband = $x[1];
		$cs = urldecode($x[2]);
		$gruppe = $x[3];
		$url = "http://$verband.liga.nu/cgi-bin/WebObjects/nuLigaHBDE.woa/wa/groupPage?championship=".urlencode($cs)."&group=$gruppe";
		//ex($url);die;
	} else {
		//print_r("else ".$url);die;
		$url = "";
	}
	$u = $url;
	$spielplanverein = 0;
}

if ($spielplanverein) {
	$spielplan = 0;
	if (!$verband) $verband = "bhv-handball";
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
	$u = "http://$verband.liga.nu/cgi-bin/WebObjects/nuLigaHBDE.woa/wa/clubMeetings?searchTimeRange=2&searchType=1{$s}&club={$club}&searchMeetings=Suchen";
}

if ($u && ($gruppe || $club)) {
	if ($spielplan) {
		if ($verband) 
		$u = "http://$verband.liga.nu/cgi-bin/WebObjects/nuLigaHBDE.woa/wa/groupPage?displayTyp=vorrunde&displayDetail=meetings&championship=".urlencode($cs)."&group=$gruppe";
		else
		$u = "http://bhv-handball.liga.nu/cgi-bin/WebObjects/nuLigaHBDE.woa/wa/groupPage?displayTyp=vorrunde&displayDetail=meetings&championship=".urlencode($cs)."&group=$gruppe";
		if ($aktuell) $u .= "&aktuell=1";
	}
	// von nuliga lesen
	$r = get_cache($u);
	if (!$r) {
		$nu = new NuLiga;
		if ($spielplan) {
			$r = $nu->get($u, $u);
			$u2 = str_replace("vorrunde", "rueckrunde", $u);
			$r .= $nu->get($u2, $u2);
			$tab = new NuPlan2;
			$tab->set_base($u);
			if ($aktuell) $tab->set_aktuell(true);
		} else {
			$r = $nu->get($u, $u);
			if ($spielplanverein) {
				$tab = new NuPlan;
				$tab->set_base($u);
			} else {
				$tab = new NuTab;
			}
		}
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
	//pp($r);die;
	//$r = file_get_contents($u);
	//if (!$utf) $r = utf8_decode($r);
	//print_r(htmlentities($r)); die;
}

if (!$r and $spielplanverein) $r = "<error>Keine Spiele im Zeitraum gefunden ($ss-$ee)</error>";
else if (!$r) $r = "<error>Konnte Tabelle bei NuLiga nicht finden</error>";
if (!$u) $r = "<error>Keine URL angegeben</error>";

// nach JSON Konvertieren und zurückliefern
error_reporting(E_ALL & ~(E_NOTICE|E_WARNING));
if ($jn) {
	try {
	$doc = new SimpleXMLElement($r);
	$r = json_encode($doc);
	// empty objects to empty strings {} => ""
	$r = str_replace("{}", '""', $r);
	} catch (Exception $e) {
		$r = "";
	}
} else {
	require_once("BadgerFish.php");
	$f = new BadgerFish;
	$doc = new DOMDocument();
	try {
	$doc->loadXML($r);
	$r = $f->encode($doc);
	} catch (Exception $e) {
		$r = "";
	}
}
//print_r($r); die;

if (!$r or $r == "[]") {
	if ($jn)
	$r = '{error: "Keine gueltigen Daten unter dieser Adresse"}';
	else
	$r = "{error: {\$:\"Keine gueltigen Daten unter dieser Adresse\"}}";
	//@unlink("cache/nu_state.txt");
}
// set content type
if ($cty) header("Content-Type: $cty");
else if ($jh) header("Content-Type: application/json; charset=utf-8");
else header("Content-Type: application/javascript; charset=utf-8");

if ($callback) echo "$callback($r)";
else echo "$r";
?>
