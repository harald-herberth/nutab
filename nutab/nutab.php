<?php
/*
Copyright (C) 2004 Harald Herberth
Licence BSD
Darf frei verwendet werden, solange damit keine Werbung in die erzeugten Tabellen eingefügt wird
 */
/*
* liest eine nuliga tabelle und bietet diese in verschiedenen Formaten an
* - mal als XML für fetch_table
*
* Ist so komisch, weil stammt noch aus einer Zeit vor nuLiga, wie wir noch ISS3 und sport4 verwendet haben
* deshalb auch XML mit diesen Feldnamen
*/
if (!function_exists("pp")) {
function pp($v) {
	$x = htmlspecialchars(print_r($v,true)); $x = str_replace("[","<br>[",$x); echo$x . "<br><br>";
}
}

// Tabelle
class NuTab {
function set_base($u) {
	$this->base = $u;
}
function init($s) {
	// hier haben wir mehrere <table> drin, eine für die Tabelle, und 2 für Spielplan nachher und vorher?
	$a = array();
	if(1 > preg_match_all(';<table.*</table;ismU', $s, $x)) return;
	//print_r($x);
	$tab = "";
	$sn = "";
	$s = $x[0][0];
	if (preg_match(';>Rang<|>Raster<;ismU', $s)) {
		$tab = $s;
	}
	if (preg_match(';>Tag;ismU', $s)) {
		$sn = $s;
	}
	$s = $x[0][1];
	if (preg_match(';>Rang<;ismU', $s)) {
		$tab = $s;
	}
	if (preg_match(';>Tag;ismU', $s)) {
		$sn = $s;
	}
	if (!$tab) return;
	if ($tab && 1 > preg_match_all(';<tr.*</tr;ismU', $tab, $x)) return;
	$rows = $x[0];
	//echo "<pre>" . htmlentities(print_r($tab,true));die;
	array_shift($rows);
	foreach($rows as $row) {
		if (1 > preg_match_all(';<td.*</td;ismU', $row, $x)) return;
		$x = $x[0];
		foreach ($x as $i => $v) {
			$x[$i] = preg_replace(';<.*(>|$);ismU', '', $v);
			$x[$i] = trim($x[$i]);
		}
		$platz = $x[1];
		$tore = explode(":", $x[7]);
		$punkte = explode(":", $x[9]);
		if (count($x) <= 5) {
			$platz = 255; // Ex, also zurückgezogen. 254 wäre AK
			if (preg_match(';Konkurrenz;', $row)) $platz = 254; 
			$o = array(
				"Platz" => $platz, 
				"Team_Kurzname" => $x[2], 
				"Spiele" => "", 
				"SpieleGewonnen" => "", 
				"SpieleUnentschieden" => "", 
				"SpieleVerloren" => "", 
				"PlusPunkte" => "", 
				"MinusPunkte" => "", 
				"PlusTore" => "", 
				"MinusTore" => "", 
				"DiffTore" => "" 
			);
		} else {
			$o = array(
				"Platz" => $platz, 
				"Team_Kurzname" => $x[2], 
				"Spiele" => $x[3], 
				"SpieleGewonnen" => $x[4], 
				"SpieleUnentschieden" => $x[5], 
				"SpieleVerloren" => $x[6], 
				"PlusPunkte" => (int) $punkte[0], 
				"MinusPunkte" => (int) $punkte[1], 
				"PlusTore" => (int) $tore[0], 
				"MinusTore" => (int) $tore[1], 
				"DiffTore" => $x[8] 
			);
		}
		$a[] = $o;
		//pp($o);
	}
	$this->a = $a;
	// Spiele auch noch parsen
	if ($sn) {
		$s = new NuPlan2;
		$s->set_base($this->base);
		$s->init($sn);
		$this->sn = $s;
	}
	//pp($this);die;
	return;
}
function get_xml() {
	if (!is_array($this->a) || !count($this->a)) return "";
	$x = "<Aktueller_Spielplan>\n";
	foreach($this->a as $a) {
		$x .= "<Tabelle>\n";
		foreach($a as $i => $v) {
			$x .= "<{$i}>$v</{$i}>\n";
		}
		$x .= "</Tabelle>\n";
	}
	// Spieldatum 2012-01-30Thh:mm:00+0200
	$z = date('Y-m-d\TH:i:00+0200', time()+1*60*60);
	$ks = "";
	$vs = "";
	if ($this->sn) {
		foreach($this->sn->a as $a) {
			if ($a["Spieldatum"] <= $z) {
				$vs .= "<vorherige_Spiele>\n";
				foreach($a as $i => $v) {
					$vs .= "<{$i}>$v</{$i}>\n";
				}
				$vs .= "</vorherige_Spiele>\n";
			} else {
				$ks .= "<kommende_Spiele>\n";
				foreach($a as $i => $v) {
					$ks .= "<{$i}>$v</{$i}>\n";
				}
				$ks .= "</kommende_Spiele>\n";
			}	
		}
	}
	if ($vs) $x .= $vs;
	if ($ks) $x .= $ks;
	$x .= "</Aktueller_Spielplan>\n";
	$x = utf8_encode($x);
	return $x;
}

}

// Vereinsplan
class NuPlan {
function set_base($u) {
	$this->base = preg_replace(';/cgi-bin.*;', "", $u);
}
function init($s) {
	$a = array();
	// hier ist ein Fehler in match_all mit U drin, falls das zu lang wird, wird ein Fehler zurückgegeben und $x ist leer
	//if (1 > preg_match_all(';Begegnungen.*<table.*</table;ismU', $s, $x)) return "";
	if (1 > preg_match_all(';<table;ismU', $s, $x)) return "";
	//print_r($x);
	preg_match_all(';<table.*</table;ism', $s, $x);
	$s = $x[0][0];
	if (1 > preg_match_all(';<tr.*</tr;ismU', $s, $x)) return "";
	$rows = $x[0];
	if (preg_match(';Halle.*Nr\..*Heim;ismU', $rows[0])) $keineSpNummer = 0; else $keineSpNummer = 1;
	array_shift($rows);
	$datum = date("d.m.Y");
	foreach($rows as $row) {
		// tag, datum, zeit, halle, [Nr,] Liga, Heim, Gast, sr/tore, sbb, genehmigt
		//  0     1      2      3      4   5      6     7     8       9
		if (1 > preg_match_all(';<td.*</td;ismU', $row, $x)) return "";
		$x = $x[0];
		// bei termin offen haben wir eine Spalte weniger, und kein Datum
		if (preg_match('/Termin offen/ismU', $x[0])) {
			array_shift($x);
			array_unshift($x, "");
			array_unshift($x, "");
			$x[2] = "00:02";
		}
		// falls keine Spielnummer da ist, eine 0 einfügen
		if ($keineSpNummer) {
			array_splice($x, 4, 0, array("0"));
		}
		$sr = $x[8];
		$sbb = $x[9];
		preg_match('/href="(.*?)"/', $sbb, $sbb); $sbb = $sbb[1]; $sbb = $this->base . $sbb;
		$sbb = base64_encode($sbb);
		if (!$this->base) $sbb = "";
		foreach ($x as $i => $v) {
			$x[$i] = preg_replace(';<.*(>|$);ismU', '', $v);
			$x[$i] = str_replace('&nbsp;', ' ', $x[$i]);
			$x[$i] = trim($x[$i]);
		}
		if ($x[0]) $tag = $x[0];
		if ($x[1]) $datum = $x[1];
		if ($x[2]) $zeit = substr(trim($x[2]),0,5);
		if (preg_match(';u;ismU', $x[2])) $zeit = "00:00";
		if (preg_match(';kampflos;ismU', $row)) $zeit = "00:01";
		preg_match(';(\d\d)\.(\d\d)\.(\d\d\d\d);ismU', $datum, $xx);
		$da = "{$xx[3]}.{$xx[2]}.{$xx[1]}";
		$dax = "{$xx[3]}-{$xx[2]}-{$xx[1]}T{$zeit}:00+0200";
		//2008-05-03T17:45:00+02:00 2008.05.03
		$halle_nr = (int) $x[3];
		$halle = $x[3];
		$tore_heim = "";
		$tore_gast = "";
		$tore = explode(":",trim($x[8]));
		if (count($tore) == 2) {
			$tore_heim = $tore[0];
			$tore_gast = $tore[1];
		}
		$wertung = "";
		if (preg_match(';<span title=;', $sr) and strlen($x[8]) > 2 and !preg_match(";:;", $x[8])) {
			// SR sind hier eingetragen
			// tbd oder auch die wertung
			preg_match(';</span>\s*?(.*)\s*?</td;ismU', $sr, $xx);
			if ($xx[1]) {
				$wertung = strtoupper(trim(str_replace("&nbsp;", " ", $xx[1])));
			}
			$x[8] = "";
		}
		else if (preg_match(';[a-zA-Z];ismU', $x[8])) {
			$wertung = strtoupper($x[8]);
		}
		$o = array(
			"Liga_Nummer" => "",
			"Liga_Name_kurz" => $x[5],
			"Liga_Name_lang" => $x[5],
			"Spieldatum" => $dax,
			"Spieltag" => "",
			"SpieldatumTag" => $da,
			"Spielnummer" => $x[4],
			"HeimTeam_Name_lang" => $x[6],
			"HeimTeam_Name_kurz" => $x[6],
			"Heim_ak" => "",
			"GastTeam_Name_lang" => $x[7],
			"GastTeam_Name_kurz" => $x[7],
			"Gast_ak" => "",
			"Halle_Nummer" => $halle_nr,
			"Halle_Name_lang" => "",
			"Halle_Name_kurz" => "",
			"Halle_Kuerzel" => $halle,
			"Halle_Abk" => $halle_abk,
			"Punkte_Heim" => "",
			"Tore_Heim" => $tore_heim,
			"Punkte_Gast" => "",
			"Tore_Gast" => $tore_gast,
			"Heim" => $tore_heim,
			"Gast" => $tore_gast,
			"Wertung" => $wertung,
			"sbb" => "$sbb",
		);
		//if (!preg_match(';z;ismU', $x[9])) $a[] = $o;
		if (!preg_match(';z;ismU', $wertung)) $a[] = $o;
		//pp($o);
	}
	$this->a = $a;

	//echo htmlentities($s);
	return "";
}
function get_xml() {
	if (!is_array($this->a) || !count($this->a)) return "";
	$x = "<Spielplan>\n";
	foreach($this->a as $a) {
		$x .= "<Spielplan>\n";
		foreach($a as $i => $v) {
			$x .= "<{$i}>$v</{$i}>\n";
		}
		$x .= "</Spielplan>\n";
	}
	$x .= "</Spielplan>\n";
	$x = utf8_encode($x);
	return $x;
}
}

// Liga Plan oder Pokal Plan oder Entscheidungsspiele (nur Tabelle und Spiele auf einer Seite)
class NuPlan2 {
function set_aktuell($a) {
	$this->aktuell = true;
}
function set_base($u) {
	$this->base = preg_replace(';/cgi-bin.*;', "", $u);
}
function init($s) {
	$a = array();
	$hallen = array();
	$this->a = $a;
	$this->hallen = $hallen;
	$pokal = 0;
	$ent = 0;
	$heute = date("Y.m.d");
	// wir koennen eine oder zwei runden drin haben, jeweils eine tabelle
	// bei Pokal kann Tabelle und Spielplan drin sein, oder nur Spielplan, optional mit xtelfinale als erst zeile
	// oder es sind Entscheidungsspiele, dann sind zwei Tabellen drin, Tabelle und Spielplan
	$ntab = preg_match_all(';<table.*</table;ismU', $s, $x);
	$x = $x[0];
	if ($ntab < 1 or $ntab > 2) return "";
	if (preg_match(';Molten-Cup|Bezirkspokal;ismU', $s)) $pokal = 1;
	if ($pokal and $ntab <> 1) return "";
	if ($ntab == 2 and preg_match(';Rang.*Mannschaft.*Begegnungen;ismU', $x[0]) and 
			preg_match(';Tag Datum Zeit.*Heimmannschaft.*Gastmannschaft;ismU', $x[1])) {
		$ent = 1;
		$ntab = 1;
		array_shift($x[0]);
	}
	if (1 > preg_match_all(';<tr.*</tr;ismU', $x[0], $x1)) return "";
	$rows = $x1[0];
	if ($ntab == 1 and preg_match(';Tag Datum Zeit.*Heimmannschaft.*Gastmannschaft;ismU', $rows[1])) {
		array_shift($rows);
	}
	if (preg_match(';Halle.*Nr\..*Heim;ismU', $rows[0])) $keineSpNummer = 0; else $keineSpNummer = 1;
	if ($ntab > 1) {
		if (1 > preg_match_all(';<tr.*</tr;ismU', $x[1], $x2)) return "";
		$rows = array_merge($x1[0], $x2[0]);
	}
	$datum = date("d.m.Y");
	foreach($rows as $row) {
		// tag, datum, zeit, halle, [Nr,] Heim, Gast, sr/tore(oder wertung), Bericht, genehmigt?
		//  0     1      2    3      4   5      6       7                     8      9
		// wenn nur die <th> header, oder nur ein <td> bei Pokal die Runde, => weiter
		if (2 > preg_match_all(';<td.*</td;ismU', $row, $x)) continue; 
		$x = $x[0];
		// bei termin offen haben wir eine Spalte weniger, und kein Datum
		if (preg_match('/Termin offen/ismU', $x[0])) {
			array_shift($x);
			array_unshift($x, "");
			array_unshift($x, "");
			$x[2] = "00:02";
		}
		// falls keine Spielnummer da ist, eine 0 einfügen
		if ($keineSpNummer) {
			array_splice($x, 4, 0, array("0"));
		}
		$sr = $x[7];
		$sbb = $x[8];
		preg_match('/href="(.*?)"/', $sbb, $sbb); $sbb = $sbb[1]; $sbb = $this->base . $sbb;
		$sbb = base64_encode($sbb);
		if (!$this->base) $sbb = "";
		//pp($sbb);die;
		foreach ($x as $i => $v) {
			$x[$i] = str_replace("\r\n", "", $x[$i]);
			$x[$i] = str_replace("\r", "", $x[$i]);
			$x[$i] = str_replace("\n", "", $x[$i]);
			$x[$i] = preg_replace(';<.*(>|$);ismU', '', $x[$i]);
			$x[$i] = str_replace('&nbsp;', ' ', $x[$i]);
			$x[$i] = trim($x[$i]);
		}
		if ($x[0]) $tag = $x[0];
		if ($x[1]) $datum = $x[1];
		$zeit = "00:00";
		if ($x[2]) $zeit = substr(trim($x[2]),0,5);
		//if ($pokal and $zeit == '00:00') continue; // bei Pokal nehmen wir erst Spiele, die angesetzt sind
		if ($pokal and !$x[6]) continue; // bei Pokal nehmen wir nur Spiele mit Gast (rest freilos)
		if (preg_match(';u;ismU', $x[2])) $zeit = "00:00";
		if (preg_match(';kampflos;ismU', $row)) $zeit = "00:01";
		preg_match(';(\d\d)\.(\d\d)\.(\d\d\d\d);ismU', $datum, $xx);
		$da = "{$xx[3]}.{$xx[2]}.{$xx[1]}";
		$dax = "{$xx[3]}-{$xx[2]}-{$xx[1]}T{$zeit}:00+0200";
		//2008-05-03T17:45:00+02:00 2008.05.03
		$x[5] = str_replace("'","",$x[5]);
		$x[6] = str_replace("'","",$x[6]);
		//if ($pokal and preg_match(';Sieger|Spiel\s+230;ismU', $x[5] . $x[6])) continue; // bei Pokal nehmen wir erst Spiele, die zwei richige Gegner haben
		$halle_nr = (int) $x[3];
		$halle = $x[3];
		$tore_heim = "";
		$tore_gast = "";
		$tore = explode(":", $x[7]);
		if (count($tore) == 2) {
			$tore_heim = $tore[0];
			$tore_gast = $tore[1];
		}
		$wertung = "";
		$sr1 = $sr2 = "";
		if (preg_match(';<span title=;', $sr) and strlen($x[7]) > 2 and !preg_match(";:;", $x[7])) {
			// SR sind hier eingetragen, oder SR und Spielwertung 
			// bei SR ist der title im span
			// bei wertung ist der title im td, und es kommt noch was nach dem sr span
			// falls hinter dem span noch was ist, ist dies die Wertung
			preg_match(';</span>\s*?(.*)\s*?</td;ismU', $sr, $xx);
			if ($xx[1]) {
				$wertung = strtoupper(trim(str_replace("&nbsp;", " ", $xx[1])));
			} else {
				preg_match(';span title="(.*)";ismU', $sr, $xx);
				$xx = preg_split(';\s*/\s*;ism', $xx[1]);
				list($sr1, $sr2) = $xx;
			}
			$x[7] = "";
		}
		else if (preg_match(';[a-zA-Z];ismU', $x[7])) {
			$wertung = strtoupper(trim($x[7]));
		}

		$spn = $x[4];
		$o = array(
			"Liga_Nummer" => 0,
			"Liga_Name_kurz" => "todo",
			"Liga_Name_lang" => "todo",
			"Spieldatum" => $dax,
			"Spieltag" => 0,
			"SpieldatumTag" => $da,
			"SpielZeit" => $zeit,
			"Spielnummer" => $spn,
			"HeimTeam_Name_lang" => $x[5],
			"HeimTeam_Name_kurz" => $x[5],
			"Heim_ak" => "",
			"GastTeam_Name_lang" => $x[6],
			"GastTeam_Name_kurz" => $x[6],
			"Gast_ak" => "",
			"Halle_Nummer" => $halle_nr,
			"Halle_Name_lang" => "",
			"Halle_Name_kurz" => "",
			"Halle_Kuerzel" => $halle,
			"Halle_Abk" => $halle_abk,
			"Punkte_Heim" => "",
			"Tore_Heim" => $tore_heim,
			"Punkte_Gast" => "",
			"Tore_Gast" => $tore_gast,
			"Heim" => $tore_heim,
			"Gast" => $tore_gast,
			"Wertung" => $wertung,
			"SR1" => $sr1,
			"SR2" => $sr2,
			"sbb" => "$sbb",
		);
		if (!preg_match(';z;ismU', $wertung)) {
			$a[] = $o;
			if ($heute <= $da) $this->aktiv = 1;
			if ($this->von > $da && $zeit > "00:05") $this->von = $da;
			if ($this->bis < $da && $zeit > "00:05") $this->bis = $da;
		}
	}
	//pp($a); die;
	$this->a = $a;
	if ($this->aktuell) $this->filter();
	//echo htmlentities($s);
	return "";
}

function bucket($d) {
	return (int) (($d - $this->base) / 604800);
}
function filter() {
	// versucht den aktuellen Spieltag zu bestimmen, und diesen und den Spieltag davor zu erhalten
	// alle Spiele in buckets von Mi-Di unterteilen 1.8.12 ist ein Mittwoch
	// SpieldatumTag":{"$":"2012.09.15"},"
	$b = array();
	unset($spiel);
	$this->base = mktime(0,0,0,8,1,2012);
	$heute = $this->bucket(mktime(0,0,0));
	foreach($this->a as &$spiel) {
		$tag = mktime(0,0,0,substr($spiel['SpieldatumTag'],5,2),
			substr($spiel['SpieldatumTag'],8,2),substr($spiel['SpieldatumTag'],0,4));

		$bucket = $this->bucket($tag);
		$spiel['bucket'] = $bucket;
		$b[$bucket] = 1;
		//print("$tag  " . $spiel['SpieldatumTag'] ." Bucket: ".(($tag-$heute)/7/24/3600)."<br>");
	}
	$b = array_keys($b);
	sort($b);
	array_unshift($b, 0); // sentinels an beide enden
	array_push($b, 1000000);
	foreach($b as $i => $v) {
		if ($v >= $heute) {
			$bcurr = $v;
			$bprev = $b[$i-1];
			break;
		}
	}
	//echo "curr $bcurr prev $bprev <br>";
	$a = array();
	foreach($this->a as $i => &$spiel) {
		if ($spiel['bucket'] != $bcurr and $spiel['bucket'] != $bprev)
			unset($this->a[$i]);
		else
			unset($this->a[$i]['bucket']);
	}
}
function get_xml() {
	if (!is_array($this->a) || !count($this->a)) return "";
	$x = "<Spielplan>\n";
	foreach($this->a as $a) {
		$x .= "<Spielplan>\n";
		foreach($a as $i => $v) {
			$x .= "<{$i}>$v</{$i}>\n";
		}
		$x .= "</Spielplan>\n";
	}
	$x .= "</Spielplan>\n";
	$x = utf8_encode($x);
	return $x;
}

}
?>
