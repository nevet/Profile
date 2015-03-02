<?php
session_start();

require_once("solver.php");
require_once("../../config.php");

$puzzle = array_fill(0, 5, array_fill(0, 5, 4));
$startPosR;
$startPosC;
$db;

function isAjax() {
  return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
         strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

function randomizeObstacle($obstacle) {
  global $puzzle;

  $coordr = rand(0, 4);
  $coordc = rand(0, 4);

  while ($obstacle > 0) {
    while ($puzzle[$coordr][$coordc] != 4) {
      $coordr = rand(0, 4);
      $coordc = rand(0, 4);
    }

    $puzzle[$coordr][$coordc] = 5;

    $obstacle--;
  }
}

function randomizeCheckPoint() {
  global $puzzle, $startPosR, $startPosC;

  for ($i = 0; $i < 5; $i ++) {
    $cur = $i == 4 ? 5 : $i;
    
    $coordr = rand(0, 4);
    $coordc = rand(0, 4);

    while ($puzzle[$coordr][$coordc] != 4) {
      $coordr = rand(0, 4);
      $coordc = rand(0, 4);
    }

    $puzzle[$coordr][$coordc] = $cur;

    if ($cur == 0) {
      $startPosR = $coordr;
      $startPosC = $coordc;
    }
  }
}

function stringify() {
  global $puzzle;

  $str = "";

  for ($i = 0; $i < 5; $i ++) {
    for ($j = 0; $j < 5; $j ++) {
      $str = $str . $puzzle[$i][$j];
    }
  }

  return $str;
}

function generateNewPuzzle() {
  global $puzzle, $startPosR, $startPosC;

  randomizeObstacle(rand(4, 7));
  randomizeCheckPoint();

  $solution = solve($startPosR, $startPosC, $puzzle);

  while ($solution["bestCount"] > 25)
  {
    $puzzle = array_fill(0, 5, array_fill(0, 5, 4));
    randomizeObstacle(rand(4, 7));
    randomizeCheckPoint();

    $solution = solve($startPosR, $startPosC, $puzzle);
  }

  $map = stringify();
  
  $_SESSION["map"] = $map;
  $_SESSION["startTime"] = microtime(true);
  $_SESSION["bestCount"] = $solution["bestCount"]; 
  $_SESSION["solution"] = $solution["solution"];

  $return["puzzle"] = $puzzle;

  $return["startPosR"] = $startPosR;
  $return["startPosC"] = $startPosC;

  echo $map."<br>";
  echo json_encode($return);
}

function endGame() {
  global $db;

  $db = new mysqli(db_host, db_uid, db_pwd, db_name);
  $map = $_SESSION["map"];
  $timeElapse = microtime(true) - $_SESSION["startTime"];
  $userStep = $db->escape_string($_REQUEST["userStep"]);

  $res = $db->query("SELECT * FROM PUZZLE WHERE MAP='" .$map."'");

  if ($res->num_rows == 0) {
    $res = $db->query("INSERT INTO PUZZLE VALUES('".$map."',". $_SESSION["bestCount"]. ", $userStep, $timeElapse)");
  } else {
    $row = $res->fetch_assoc();
    $curUserBestStep = $row["USER_BEST_STEP"];
    $curUserBestTime = $row["USER_BEST_TIME"];

    if ($userStep < $curUserBestStep) {
      $res = $db->query("UPDATE PUZZLE SET USER_BEST_STEP=$userStep, USER_BEST_TIME=$timeElapse WHERE MAP='" .$map ."'");
      if (!$res) {
        exit("MySQL reports " . $db->error);  
      }
    } else
    if ($userStep < $curUserBestStep && $timeElapse < $curUserBestTime) {
      $res = $db->query("UPDATE PUZZLE SET USER_BEST_TIME=" .$timeElapse ." WHERE MAP='" .$map ."'");
      if (!$res) {
        exit("MySQL reports " . $db->error);  
      }
    }
  }

  $db->close();

  echo $_SESSION["bestCount"];
}

// if (isAjax()) {
  // for GET
  if (isset($_GET["cmd"]) && !empty($_GET["cmd"])) {
    $command = $_GET["cmd"];

    switch ($command) {
      case "new":
        generateNewPuzzle();
        break;
      case "bestCount":
        endGame();
        break;
      case "solution":
        echo json_encode(array("bestCount" => $_SESSION["bestCount"],
                               "solution" => $_SESSION["solution"]));
        break;
      default:
        echo "wrong";
        break;
    }
  }
// }
?>