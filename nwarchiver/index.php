<?php
//소스 다운로드
if($_GET['sourcedl']) {
  $filepath = './' . basename(__FILE__);
  $filesize = filesize($filepath);
  $path_parts = pathinfo($filepath);
  $filename = $path_parts['basename'];
  $extension = $path_parts['extension'];

  header("Pragma: public"); header("Expires: 0");
  header("Content-Type: application/octet-stream");
  header("Content-Disposition: attachment; filename='$filename'");
  header("Content-Transfer-Encoding: binary");
  header("Content-Length: $filesize");

  ob_clean();
  flush();
  readfile($filepath);
}

//설정 로드
include 'config.php';
?>
<html>
<head>
<title>나무위키 박제기</title>
<meta name="viewport" content="width=device-width">
<meta charset="utf-8">
<span style="float:right;"><a href="?sourcedl=1">소스 다운로드</a> <a href=".">목록 표시</a> <a href="..">인간.kr</a></span>
<h2 id="title">나무위키 박제기</h2>
</head>
<body>
<form name="archive" action="" method="GET">
  <p>https://namu.wiki/
  <input type="text" name="docname" placeholder="w/문서명"<? if($disabled) echo ' disabled' ?> />
  <button<? if($disabled) echo ' disabled' ?>>박제</button></p>
  <input type="hidden" name="save" value="1">
</form>
<form name="search" action="" method="GET">
  <p><input type="text" name="search" placeholder="검색어" />
  <button>검색</button>
  <label><input type="checkbox" name="contain" />포함</label></p>
</form>
<?php
//설정 파일 로드 여부 확인
if(!$user and !$dbname and !$password) {
  echo '❌설정 파일이 로드되지 않았습니다.<br>같은 경로에 config.php 파일이 있는지 확인해 주시기 바라며, 만약 없을 경우 <a href="https://github.com/Ingan121/NWArchiver/blob/master/nwarchiver/config.php">이곳</a>에서 config.php 파일을 받아 수정한 후 같은 경로에 업로드 해 주시기 바랍니다.<br>';
}

//DB 접속
$mysqli = new mysqli('localhost', $user, $password, $dbname);
mysqli_query($mysqli, 'set names utf8mb4');
if($mysqli->connect_error) {
  echo '❌오류: DB 접속 실패';
}

//nwarchiver 테이블이 존재하는지 확인하고 없으면 생성
$result = $mysqli->query("SHOW TABLES LIKE 'nwarchiver'");
$exist = ( $result->num_rows > 0 );
if(!$exist) {
  $sql = 'CREATE TABLE nwarchiver (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(2000) CHARACTER SET utf8mb4,
    wikitext MEDIUMTEXT CHARACTER SET utf8mb4
  )';
  if (mysqli_query($mysqli, $sql)) {
  echo ' ✔테이블 생성됨';
  } else {
  echo ' ❌테이블 생성 실패: ' . htmlspecialchars(mysqli_error($mysqli));
  }
}

if($_GET['save']) {
  //특정 문자열 사이의 문자열 가져오는 함수
  function get_string_between($string, $start, $end) {
  $string = ' ' . $string;
  $ini = strpos($string, $start);
  if ($ini == 0) return '';
  $ini += strlen($start);
  $len = strpos($string, $end, $ini) - $ini;
  return substr($string, $ini, $len);
  }
  //페이지 긁어오기
  $regex = '#(<span class=\'wiki-image-align-).*?(</noscript></span></span>)#m';
  $url = 'https://namu.wiki/' . str_replace('+', '%20', str_replace('%2F', '/', urlencode($_GET['docname'])));
  echo '<a href="'.$url.'">원본 페이지 보기</a>';
  $html = file_get_contents($url);
  $wikitext = '<h1' . get_string_between($html, '<h1', '<footer');
  $wikitext = preg_replace($regex, '[이미지]', $wikitext);
  $wikitext = str_replace('/w/', 'https://namu.wiki/w/', $wikitext);
  $wikitext = str_replace($_SERVER['SERVER_ADDR'], '0.0.0.0', $wikitext);
} elseif($_GET['load']) {
  //저장된 페이지 불러오기
  $sql = 'SELECT * FROM nwarchiver WHERE id = ' . $_GET['load'];
  $result = mysqli_query($mysqli, $sql);
  if ($result) {
    $archivelist = mysqli_fetch_array($result);
    echo '<a href="' . $archivelist['url'] . '">원본 페이지 보기</a><br><hr>';
    echo $archivelist['wikitext'];
  } else {
    echo '❌오류: ' . htmlspecialchars(mysqli_error($mysqli));
  }
}

//저장
if($_GET['save'] == '1' and $wikitext != '<h1') {
  $sql = 'INSERT INTO nwarchiver (url, wikitext) VALUES ("' . mysqli_real_escape_string($mysqli, $url) . '","' . mysqli_real_escape_string($mysqli, $wikitext) . '")';
  if (mysqli_query($mysqli, $sql)) {
  echo ' ✔박제됨';
  } else {
  echo ' ❌오류: ' . htmlspecialchars(mysqli_error($mysqli));
  }
  echo '<br><hr>' . $wikitext;
} else if($wikitext == '<h1') {
  echo ' ❌오류: 불러올 수 없는 페이지';
  echo $html;
}

//목록 불러오기
if(empty($_GET['save']) and empty($_GET['load'])) {
  echo '<hr>';
  $sql = 'SELECT * FROM nwarchiver';
  if($_GET['search']) {
    $search = mysqli_real_escape_string($mysqli, str_replace('+', '%20', str_replace('%2F', '/', urlencode($_GET['search']))));
    if($_GET['contain']) {
      $sql = $sql . ' WHERE url LIKE "%' . $search . '%"';
    } else {
      $sql = $sql . ' WHERE url LIKE "' . $search . '"';
    }
  }
  $result = mysqli_query($mysqli, $sql);
  if (!$result) {
    echo '❌오류: ' . htmlspecialchars(mysqli_error($mysqli));
  } else {
    echo '<table border=1><tr><th>ID</th><th>원본 URL</th><th style="width:1%; white-space:nowrap;">옵션</th></tr>';
    while($archivelist = mysqli_fetch_array($result)) {
      echo '<tr><td>' . $archivelist['id'] . '</td><td style="word-break:break-all;"><a href="' . $archivelist['url'] . '">' . urldecode($archivelist['url']) . '</a></td><td><a href="?load=' . $archivelist['id'] . '">보기</a></tr>';
    }
    echo '</table>';
  }
}
?>
<script>
  document.getElementById('title').onclick = function() {
    document.title='좆무위키 박제기';
    document.body.outerHTML = document.body.outerHTML.replace('나무위키', '좆무위키');
  };
</script>
</body>
<footer style="text-align:center;">
  <hr>
  각 페이지는 나무위키에서 퍼왔습니다.
  <br>
  <a href="license.txt">Made by Ingan121
  <br>
  Licensed under The MIT License</a>
</footer>
</html>
