<?php
/*
	Erzeugt eine html-Datei mit den Tabellen aller gewünschten klassen
	Diese Datei wird dann mittels dem JavaScipt die Tabellen auch holen und anzeigen
	Parameter q codiert was anzuzeigen ist
	q=key:value,key:value;... für weitere Tabellen
	
	Über eine Eingabemaske kann die URL?... auch interaktiv zusammengestellt werden
*/
// lesen der Parameter
$q = $_GET["q"];
function charset($x) {
	// input immer nach ascii iso
	$pattern = implode("|", array_map("utf8_encode", str_split("äöüÄÖÜß")));
	if (preg_match("/$pattern/ism", $x)) {
		// da ist utf8 ö drin
		$x = utf8_decode($x);
	}
	return $x;
}
//echo phpinfo();exit;
$q = charset($q);
$h = "";
if (!$q) $h = <<<END
END;
//if (!$q) $q = "title:Landesliga Frauen Nord,klasse:LL FN,verein:Zirndorf;klasse:BYF,verein:Heroldsberg;title:3te,klasse:BYM;title:5te,klasse:ll mn";
$q = str_replace("+", " ", urldecode($q));
$t = Array();
foreach (split(";", $q) as $a) {
	$tt = Array();
	foreach (split(",", $a) as $p) {
		list($k, $v) = split(":", $p);
		$tt[$k] = $v;
	}
	$t[] = $tt;
}

$conf = 0;
if (!$q or $conf) {
// url zusammenbauen
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
        "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<title>nuLiga-Tabellen konfigurieren</title>
<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">
</head>
<body>
<style>
table {font-size: 12px; border-spacing:1px; border-collapse: collapse;}
th {font-weight: bold; background-color: #CCCCCC;}
td, th {
	border:1px solid gray;
	height:16px;
	padding:2px 5px;
	white-space:nowrap;
}
</style>
<p>Die Seite zur Konfiguration einer URL ist leider noch nicht fertig,
habe ich eigentlich noch gar nicht angefangen. 
<p>Bitte die URL selber zusammenstellen.
Da ihr das ja nur einmal machen müsst, mute ich euch das mal einfach zu.
<p>
Über den Parameter q der URL könnt ihr angeben, was ihr wie haben wollt.
<p>
http://handball-sr-mittelfranken.de/tabellen/tabellen.php?q=klasse:BYF,verein:Heroldsberg;klasse:LL+FN,verein:Zirndorf,title:Landesliga+Frauen+Nord
(als <a href="http://handball-sr-mittelfranken.de/tabellen/tabellen.php?q=klasse:BYF,verein:Heroldsberg;klasse:LL+FN,verein:Zirndorf,title:Landesliga+Frauen+Nord">Link</a>)
<p>
Nach q= folgen die Werte für alle Tabellen, wobei ";" die Angaben für mehrere Tabellen trennt. "," trennt Paare von Schlüssel-Wert. Schlüssel und Wert sind jeweils durch ":" getrennt. Ein zusätzlicher Schlüssel ("title") kann als Überschrift über die Tabelle angegeben werden. Wird dieser nicht angegeben, so werden alle Schlüssel als Titel verwendet.
<p>
Leerzeichen in der URL sollten durch "+" ersetzt werden, überall da wichtig, wo Leerzeichen in Klassen oder Titeln vorkommen.
<p>
Alle Ligen eines Vereins können auf einen Schlag ausgegeben werden, wenn die klasse, url oder liganummer 
nicht angegeben wird, sondern nur der Verein, zB
<p>
http://handball-sr-mittelfranken.de/tabellen/tabellen.php?q=verein:Altenberg,AuchSpiele=1
(als <a href="http://handball-sr-mittelfranken.de/tabellen/tabellen.php?q=verein:Altenberg,AuchSpiele=1">Link</a>)

<p>
Mehr Hilfe <a href="http://handball-sr-mittelfranken.de/tabellen">siehe hier</a>
<p>
Viel Erfolg also beim Konfigurieren, nochmal sorry, dass es nicht interaktiver gelöst ist.
<!--
<table>
<tr><th>Titel</th><th>klasse</th><th>URL</th><th>Verein</th><th>Extra</th></tr>
<?php
	foreach ($t as $tt) {
		$e = "";
		echo "<tr><td>{$tt['title']}</td><td>{$tt['klasse']}</td><td>{$tt['URL']}</td><td>{$tt['verein']}</td>$e<td></td><tr>";
	}
	echo "<tr><td></td><td></td><td></td><td></td><td></td><tr>";
	echo "<tr><td></td><td></td><td></td><td></td><td></td><tr>";
?>
</table>
-->
</body>
</html>
<?php
exit;
}


?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
        "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<title>nuLiga-Tabellen</title>
<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">
</head>
<body>
<!-- Einbinden der CSS und JS Dateien -->
<link rel="stylesheet" type="text/css" href="http://handball-sr-mittelfranken.de/tabellen/tabellen.css">
<script src="http://handball-sr-mittelfranken.de/tabellen/jquery.js" type="text/javascript"></script>
<script src="http://handball-sr-mittelfranken.de/tabellen/tabellen.js" type="text/javascript"></script>
<style>
h1 {font-size:18px;}
.tab {float:left; margin: 0 10px 0 0;}
</style>
<?php
// nur nach einem verein suchen?
$tab = "srsTab";
if (!preg_match("/plan:|planverein:|klasse:|url:|liganummer:|;/i", $q) and preg_match("/verein:/i", $q)) {
	$tab = "srsTabVerein";
}
foreach (split(";", $q) as $a) {
	echo "<!-- $a -->\n";
	if (preg_match("/plan:/",$a)) $tab = "srsPlan";
	if (preg_match("/planverein:/",$a)) $tab = "srsPlanVerein";
	$x = "<div class=\"$tab\" ";
	foreach (split(",", $a) as $p) {
		$p = preg_replace('/[^-:0-9a-zäöüÄÖÜß_ ;!#%()*+?@]/i', "", $p);
		list($k, $v) = split(":", $p);
		if ($k != "plan" and $k != "planverein") $x .= "srs{$k}=\"$v\" ";
	}
	$x .= '></div>';
	if (!preg_match("/title=/", $x)) $x = "<h1>$a</h1>" . $x;
	echo "<div class=\"tab\">$x</div>\n";
	$tab = "srsTab";
}
?>
</body>
</html>
