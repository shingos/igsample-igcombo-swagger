#!/usr/bin/php
<?php
set_time_limit(600);

$mysqlParams = array(
  'host'=>'127.0.0.1', // ini_get("mysqli.default_host")
  'username'=>'root',
  'password'=>'',
  'dbname'=>'demo'
);
$mysqlTableName = 'sample';
$textMaxlen = 255;

function main() {
  global $aozoraSource, $mysqlParams, $mysqlTableName;

  $mysqli = new mysqli($mysqlParams['host'], $mysqlParams['username'], $mysqlParams['password'], $mysqlParams['dbname']);
  $mysqli->set_charset('utf8');

  $mysqli->query("TRUNCATE TABLE `$mysqlTableName`");
  foreach($aozoraSource as $url) {
    // 文書ダウンロード
    $text = downloadAndExtractText($url);
    // テキスト抽出
    list($title, $author, $lines) = parseText($text);
    // sampleテーブルに書き込み
    insertRecords($mysqli, $title, $author, $lines);
  }

  // sample.jsonを書き出し
  exportJSONFile($mysqli);

  // NGramデータ
  $mysqli->query("TRUNCATE TABLE `sample_ngram`;");
  $mysqli->query("INSERT INTO `sample_ngram`(`id`, `title`, `author`, `text`, `text_bigram`) SELECT s.id, s.title, s.author, s.text, func_get_bigram(s.text) FROM `$mysqlTableName` AS s;");

  $mysqli->close();
}

function exportJSONFile($connection) {
  global $mysqlTableName;

  $fp = fopen(__DIR__.DIRECTORY_SEPARATOR.'sample.json', 'w');

  $result = $connection->query("SELECT * FROM `$mysqlTableName` ORDER BY `id` ASC;");
  $idx = 0;

  fwrite($fp, "[\n");
  while ($row = $result->fetch_row()) {
    if ($idx++ > 0) fwrite($fp, ",\n");
    fwrite($fp, json_encode([
      "id"=>intval($row[0]),
      "title"=>$row[1],
      "author"=>$row[2],
      "text"=>$row[3]
    ], JSON_UNESCAPED_UNICODE));
  }
  fwrite($fp, "\n]");

  fclose($fp);
}

function insertRecords($connection, $title, $author, $lines) {
  global $mysqlTableName, $textMaxlen;

  echo $title.'-'.$author." (".count($lines).")\n";

  $prepared = $connection->prepare("INSERT INTO `$mysqlTableName`(`title`, `author`, `text`) VALUES(?, ?, ?);");

  foreach($lines as $ln) {
    if (mb_strlen($ln) > $textMaxlen) $ln = mb_substr($ln, 0, $textMaxlen);
    $prepared->bind_param("sss", $title, $author, $ln);
    $prepared->execute();
  }
}

function parseText($text) {
  $title = null;
  $author = null;
  $lines = [];

  $lnum = 0;
  $ignoreS = 2;
  foreach (preg_split("/((\r?\n)|(\r\n?))/", $text) as $line) {
    $lnum++;
    if (is_null($title)) {
      $line = trim($line);
      if ($line === "") {
        if (count($lines) >= 2) {
          $author = array_pop($lines);
          if (mb_substr($author, mb_strlen($author)-1, 1) === '訳') { $author = array_pop($lines); }
          $title = implode("　", $lines);
        }
        else {
          $title = "(不明)";
          $author = "(不明)";
        }
        $lines = [];
      }
      else {
        array_push($lines, $line);
      }
      continue;
    }
    else if ($ignoreS > 0) {
      if (strpos($line, '---------------------------------------') === 0) { $ignoreS--; continue; }
    }
    else {
      $line = mb_ereg_replace("［＃[^］]*］", "", $line);
      $line = mb_ereg_replace("《[^》]*》", "", $line);
      $line = mb_ereg_replace("[　]+", " ", $line);
      $line = trim($line);
      if (strlen($line) < 1) continue;
      if (strpos($line, '底本：') === 0) { break; }

      $line = mb_ereg_replace("。", "。\n", $line);
      $mline = explode("\n", $line);
      foreach ($mline as $st) {
        $st = trim($st);
        if (mb_strlen($st) < 1) continue;
        if ($st === "」") {
          $lines[count($lines)-1] .= $st;
        } else {
          array_push($lines, $st);
        }
      }
    }
  }

  return [$title, $author, $lines];
}

function downloadAndExtractText($url) {
  $tempfile = tempnam(sys_get_temp_dir(), 'az');

  $ch = curl_init($url);
  $fp = fopen($tempfile, 'wb');
  curl_setopt($ch, CURLOPT_HEADER, false);
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);
  curl_setopt($ch, CURLOPT_FILE, $fp);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_exec($ch);
  curl_close($ch);
  fclose($fp);

  $text = null;

  $zip = new ZipArchive;
  $zip->open($tempfile, ZipArchive::CHECKCONS);

  for ($i = 0; $i < $zip->numFiles; $i++) {
    $stat = $zip->statIndex($i);
    $fp = fopen("zip://$tempfile#{$stat['name']}", "rb");
    $buf = [];
    while (!feof($fp)) {
      array_push($buf, fread($fp, $stat['size']));
    }
    fclose($fp);

    $text = mb_convert_encoding(implode('', $buf), 'UTF-8', 'SJIS');
    if (mb_strpos($text, '底本：') > 0) break;
    $text = null;
  }

  $zip->close();
  unlink($tempfile);

  return $text;
}

// -----
// https://github.com/aozorabunko/aozorabunko/raw/master/ - テキストファイル zip	JIS X 0208／ShiftJIS
$aozoraSource = [
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000291/files/42332_ruby_16188.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000291/files/59364_ruby_69715.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000906/files/42648_ruby_22949.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000906/files/42502_ruby_22671.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000906/files/42629_ruby_23000.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000270/files/1546_ruby_21459.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000270/files/1547_ruby_21460.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000270/files/1548_ruby_21461.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000270/files/1549_ruby_21462.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000270/files/1550_ruby_21463.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000270/files/1551_ruby_21464.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000270/files/1552_ruby_21465.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000270/files/1553_ruby_21466.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001346/files/49518_ruby_35742.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001475/files/50989_ruby_46359.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000329/files/18380_ruby_12075.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000134/files/710_ruby_20855.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001124/files/43413_ruby_22450.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001124/files/42937_ruby_15449.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001124/files/42939_ruby_14897.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001373/files/55998_ruby_53547.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001030/files/4816_ruby_14379.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001030/files/4810_ruby_14389.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001224/files/46117_ruby_29111.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001224/files/46071_ruby_36961.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001224/files/47486_ruby_33707.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000293/files/1848_ruby_5794.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000183/files/1890_ruby_19563.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000183/files/49719_ruby_36028.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000218/files/46212_ruby_44739.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000218/files/46214_ruby_28987.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000218/files/46218_ruby_26067.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000129/files/2595_ruby_20435.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000129/files/45224_ruby_19890.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000366/files/50919_ruby_39173.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000129/files/2599_ruby_23032.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000158/files/46459_ruby_23721.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000158/files/55958_ruby_50652.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000158/files/844_ruby_20939.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000158/files/842_ruby_4181.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000162/files/58312_ruby_68725.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000162/files/56933_ruby_62143.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000042/files/2356_ruby_4679.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000042/files/2361_ruby_4694.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000042/files/2507_ruby_13839.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000879/files/16_ruby_344.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000879/files/15_ruby_904.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000879/files/123_ruby_1199.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000879/files/67_ruby_967.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000879/files/66_ruby_400.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000879/files/45761_ruby_38235.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000879/files/72_ruby_362.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000879/files/80_ruby_753.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000879/files/79_ruby_381.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000879/files/61_ruby_2706.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000879/files/170_ruby_348.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000879/files/113_ruby_995.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000879/files/3755_ruby_27254.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000879/files/3828_ruby_28510.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000148/files/776_ruby_6020.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000148/files/773_ruby_5968.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000148/files/796_ruby_2452.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000148/files/56143_ruby_50824.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000148/files/798_ruby_2413.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000148/files/752_ruby_2438.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000148/files/783_ruby_1311.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000148/files/789_ruby_5639.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000148/files/794_ruby_4237.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000305/files/4626_ruby_10312.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000305/files/1903_ruby_4853.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000305/files/50396_ruby_38362.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000305/files/50399_ruby_39165.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000305/files/2534_ruby_39288.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000081/files/45472_ruby_35665.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000081/files/43753_ruby_17603.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000081/files/1928_ruby_17834.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000081/files/43737_ruby_19028.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000081/files/469_ruby_19942.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000081/files/43757_ruby_17734.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000081/files/43754_ruby_17594.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000081/files/46608_ruby_31998.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000081/files/46604_ruby_34528.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000885/files/3320_ruby_5935.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000885/files/3322_ruby_6459.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000885/files/3326_ruby_6455.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000885/files/3641_ruby_6445.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000885/files/3645_ruby_6437.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000885/files/3318_ruby_5950.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000885/files/3325_ruby_6457.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000885/files/3642_ruby_6451.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000885/files/3640_ruby_6453.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000096/files/531_ruby_736.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000096/files/46689_ruby_27631.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000096/files/46691_ruby_27661.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000096/files/2231_ruby_22250.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000096/files/46694_ruby_27636.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000096/files/2130_ruby_21897.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000096/files/2099_ruby_22205.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000096/files/46711_ruby_27670.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000035/files/236_ruby_19995.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000035/files/1572_ruby_19823.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000035/files/42360_ruby_34276.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000035/files/42356_ruby_15854.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000035/files/306_ruby_20008.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000035/files/276_ruby_1040.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000035/files/285_ruby_3281.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000035/files/1576_ruby_8219.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000035/files/252_ruby_20023.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000035/files/1567_ruby_4948.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000035/files/268_ruby_3172.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000035/files/312_ruby_2924.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000035/files/2295_ruby_3146.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000035/files/45688_ruby_21351.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000005/files/53194_ruby_44732.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001924/files/58230_ruby_63641.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001566/files/54905_ruby_49755.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001566/files/53812_ruby_50183.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001566/files/52505_ruby_50193.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001566/files/54830_ruby_49769.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001310/files/49630_ruby_61182.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001310/files/59413_ruby_70153.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001310/files/55609_ruby_53015.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000154/files/1627_ruby_8117.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000154/files/1615_ruby_7918.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000154/files/1651_ruby_16602.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000154/files/1612_ruby_5203.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000154/files/1652_ruby_13200.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001779/files/56673_ruby_58784.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001779/files/57515_ruby_69288.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001779/files/56686_ruby_65429.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001779/files/56649_ruby_59454.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001779/files/57193_ruby_59534.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001779/files/57511_ruby_65185.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001779/files/57405_ruby_59991.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001779/files/57343_ruby_59977.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001779/files/56670_ruby_59514.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000111/files/585_ruby_18933.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000111/files/582_ruby_18935.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000111/files/558_ruby_18946.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000111/files/555_ruby_18948.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000111/files/562_ruby_18950.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001095/files/56802_ruby_70145.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001095/files/42841_ruby_32733.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000311/files/1997_ruby_6406.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001670/files/54834_ruby_52000.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/000042/files/2450_ruby_11083.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001579/files/53495_ruby_60869.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001471/files/55546_ruby_55576.zip',
  'https://github.com/aozorabunko/aozorabunko/raw/master/cards/001798/files/56773_ruby_58724.zip'
];
// -----

main($argv);
exit(0);

