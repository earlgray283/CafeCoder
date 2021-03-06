<!DOCTYPE HTML>
<html lang="ja">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
    <title>ランキング</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/t/bs-3.3.6/jqc-1.12.0,dt-1.10.11/datatables.min.css" />
    <script src="https://cdn.datatables.net/t/bs-3.3.6/jqc-1.12.0,dt-1.10.11/datatables.min.js"></script>
    <script src="https://cdn.rawgit.com/google/code-prettify/master/loader/run_prettify.js"></script>
    <script>
        jQuery(function($) {
            $.extend($.fn.dataTable.defaults, {
                language: {
                    url: "http://cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/Japanese.json"
                }
            });
            $("#result-table").DataTable({
                order: [
                    [0, "desc"]
                ]
            });
        });
    </script>
</head>

<body>
<?php 
include_once("../template/nav.php");
include_once("../util/util.php");
echo_nav_card($_GET["contest_id"]);
?>
<table class="table table-bordered">
<thead>
    <tr>
        <th>Rank</th>
        <th>Username</th>
        <th>Point</th>
        <th>A</th>
        <th>B</th>
        <th>C</th>
        <th>D</th>
        <th>E</th>
        <th>F</th>
    </tr>
</thead>
<tbody>

<?php

if(!isset($_GET["contest_id"])){
    echo "contest_idを指定してください。";
    exit();
}

$contest_id = $_GET["contest_id"];
try{
include_once "../database/connection.php";
include_once "../util/util.php";
$con = new DBC();
}catch(Exception $e){
    echo "RANKING INIT ERROR";
    exit();
}
//update all user submit
try{
$rec = $con->prepare_execute("SELECT username, user_id, problem, code_session FROM uploads LEFT JOIN users ON uid=user_id WHERE contest_id=?",array($contest_id));
}catch(Exception $e){
    echo "DB SELECT ERROR 1";
    exit();
}
$all_path = array();
foreach($rec as $line){
    $user_code_path = get_uploaded_session_path($line["username"], $contest_id, $line["problem"], $line["code_session"]).".result";
    if(!file_exists($user_code_path)){
        continue;
    }
    try{
    $fp = fopen($user_code_path,"r");
    $csv = fgetcsv($fp);
    $result = $csv[3];
    }catch(Exception $e){
        echo "csv load error";
    }
    try{
    $con->prepare_execute("UPDATE uploads SET result=? WHERE code_session=?",array($result, $line["code_session"]),array($result));
    }catch(Exception $e){
        echo("DB UPDATE ERROR");
    }
}
//get first ac
try{
$con->prepare_execute("DROP VIEW IF EXISTS first_ac",array());
$con->prepare_execute("CREATE VIEW first_ac AS SELECT user_id AS u,result AS r,problem AS p,upload_date FROM uploads a WHERE contest_id=? AND upload_date BETWEEN (SELECT start_time FROM contests WHERE contest_id=?) AND (SELECT end_time FROM contests WHERE contest_id=?) GROUP BY user_id, problem,result,upload_date,contest_id HAVING result='AC' AND upload_date=(SELECT MIN(upload_date) FROM uploads WHERE contest_id=? AND problem=p AND user_id=u AND result=r AND upload_date BETWEEN (SELECT start_time FROM contests WHERE contest_id=?) AND (SELECT end_time FROM contests WHERE contest_id=?))  ORDER BY upload_date ASC",array($contest_id,$contest_id,$contest_id,$contest_id,$contest_id,$contest_id));
}catch(Exception $e){
    echo("DB VIEW ERROR");
    exit();
}
//get point
try{
    $rec=$con->prepare_execute("SELECT u ,username,sum(point) AS sum_point FROM first_ac,problem,users WHERE p=problem_id and problem.contest_id=? and u=uid GROUP BY u,username ORDER BY sum_point DESC",array($contest_id));
    // var_dump($rec);
    $enum_problem = array("A"=>0,"B"=>1,"C"=>2,"D"=>3,"E"=>4,"F"=>5);
    foreach ($rec as $rank => $line) {
        $now_state=$con->prepare_execute("SELECT point,p  FROM problem ,first_ac WHERE p=problem.problem_id AND contest_id=? AND u=? GROUP BY p,u,point ORDER BY p ASC",array($contest_id,$line["u"]));
        echo '<tr><th>';
        echo (int)($rank)+1;
        echo '</th>';
        echo '<th>';
        echo $line["username"];
        echo '</th>';
        echo '<th>';
        echo $line["sum_point"];
        echo '</th>';
        for($i=0,$j=0; $i < 6; $i++){
            if($enum_problem[$now_state[$j]["p"]] == $i){
                echo '<th>';
                echo $now_state[$j]["point"];
                echo '</th>';
                $j++;
            }else{
                echo '<th>';
                echo " - ";
                echo '</th>';

            }
        }
        echo '</tr>';
    }
}catch(Exception $e){
    echo "DB SELECT ERROR 2";
}
?>
</tbody>
</table>
<?php
include_once("../util/util.php");
echo_nav_card_footer();
?>
</body>