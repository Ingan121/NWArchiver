<?php
//소스 다운로드
if($_GET['sourcedl'] == 1) {
  $filepath = './index.php';
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
<form name="form" action="" method="GET">
  <p>https://namu.wiki/
  <input type="docname" name="docname" placeholder="w/문서명" />
  <button>박제</button></p>
  <input type="hidden" name="save" value="1">
</form>
<?php
//DB 접속
$user = 'ff330_Ingan121';  //MySQL 사용자명
$dbname = 'ff330_Ingan121';  //MySQL DB명
$password = '';  //MySQL 사용자 비밀번호
include $_SERVER['DOCUMENT_ROOT'].'/password.php';  //일반적인 경우 이 라인 삭제
$mysqli = new mysqli('localhost', $user, $password, $dbname);
mysqli_query($mysqli, 'set names utf8mb4');
if($mysqli->connect_error) {
  echo '❌오류: DB 접속 실패';
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
