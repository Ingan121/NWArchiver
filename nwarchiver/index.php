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
<!doctype html>
<html>
<head>
<title><?=$siteName?> 박제기</title>
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta charset="utf-8">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous"> 
<span style="float:right;"><a href="https://github.com/Ingan121/NWArchiver">GitHub</a> <a href=".">목록 표시</a> <a href="https://인간.kr/">인간.kr</a></span>
<h2 id="title"><?=$siteName?> 박제기</h2>
</head>
<body style="margin:10px;">
<form name="archive" action="" method="POST">
  <div class="form-row" style="margin-bottom:15px;">
    <div class="col-auto" style="display:flex; align-items:center;">
      <?=$rootDomain?>
    </div>
    <div class="col-5">
      <input type="text" class="form-control" name="docname" placeholder="w/문서명"<? if($disabled) echo ' disabled' ?> />
    </div>
    <div class="col-auto">
      <button class="btn btn-primary"<? if($disabled) echo ' disabled' ?>>박제</button>
    </div>
  </div>
  <input type="hidden" name="save" value="1">
</form>
<form name="search" action="" method="GET">
  <div class="form-row" style="margin-bottom:-10px;">
    <div class="col-8">
      <p><input type="text" class="form-control" name="search" placeholder="검색어" /></p>
    </div>
    <div class="col-auto">
      <p><button class="btn btn-primary">검색</button></p>
    </div>
    <div class="custom-control custom-checkbox" style="margin-left:4px; margin-top:5.5px;">
      <input type="checkbox" class="custom-control-input" name="contain" id="contain" />
      <label class="custom-control-label" for="contain">포함</label>
    </div>
  </div>
</form>
<?php
//설정 파일 로드 여부 확인
if(!$user and !$dbname and !$password) {
  echo '❌설정 파일이 로드되지 않았습니다.<br>같은 경로에 config.php 파일이 있는지 확인해 주시기 바라며, 만약 없을 경우 <a href="https://github.com/Ingan121/NWArchiver/blob/master/nwarchiver/config.php">이곳</a>에서 config.php 파일을 받아 수정한 후 같은 경로에 업로드 해 주시기 바랍니다.<br>';
}

//DB 접속
$mysqli = new mysqli('localhost', $user, $password, $dbName);
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

if($_POST['save']) {
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
  $url = $rootDomain . str_replace('+', '%20', str_replace('%2F', '/', urlencode($_POST['docname'])));
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
if($_POST['save'] == '1' and $wikitext != '<h1') {
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
    echo '<table class="table"><tr><th>ID</th><th>원본 URL</th><th style="width:1%; white-space:nowrap;">옵션</th></tr>';
    while($archivelist = mysqli_fetch_array($result)) {
      echo '<tr><td>' . $archivelist['id'] . '</td><td style="word-break:break-all;"><a href="' . $archivelist['url'] . '">' . urldecode($archivelist['url']) . '</a></td><td><a href="?load=' . $archivelist['id'] . '">보기</a></tr>';
    }
    echo '</table>';
  }
}
?>
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script> 
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
  NWArchiver 2.1
  <br>
  Made by Ingan121
  <br>
  <a href="license.txt">Licensed under The MIT License</a>
  <br>
  <a href="https://getbootstrap.com">Powered by Bootstrap</a>
</footer>
</html>
