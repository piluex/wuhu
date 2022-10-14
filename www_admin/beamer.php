<?php
include_once("bootstrap.inc.php");
$DATAFILE = "beamer.data";
$beamerData = unserialize(file_get_contents($DATAFILE));

if ($_GET["format"])
{
  switch($_GET["format"])
  {
    case "jsonp":
      {
        header("Content-type: application/javascript");
        printf("%s(%s);",$_GET["callback"]?:"wuhuJSONPCallback",json_encode($beamerData));
      }
      break;
    case "json":
    default:
      {
        header("Content-type: application/json");
        echo json_encode($beamerData);
      }
      break;
  }
  exit();
}
include_once("header.inc.php");

if ($_POST["mode"])
{
  echo "<".'?xml version="1.0" encoding="utf-8"?'.">\n";
  $out = array();
  $out["success"] = true;
  $out["result"] = array();
  $out["result"]["mode"] = $_POST["mode"];

  switch ($_POST["mode"]) {
    case "announcement":
    {
      if ($_POST["isHTML"] == "on")
      {
        $out["result"]["announcementhtml"] = $_POST["announcement"];
      }
      else
      {
        $out["result"]["announcementtext"] = $_POST["announcement"];
      }
    } break;
    case "compocountdown":
    {
      if ($_POST["compo"])
      {
        $s = get_compo( $_POST["compo"] );
        $out["result"]["componame"] = $s->name;
        $out["result"]["compostart"] = $s->start;
      }
      if ($_POST["eventname"])
      {
        $out["result"]["eventname"] = $_POST["eventname"];
        $out["result"]["compostart"] = $_POST["eventtime"];
      }

    } break;
    case "compodisplay":
    {
      $compo = get_compo( $_POST["compo"] );
      $out["result"]["componame"] = $compo->name;

      $query = new SQLSelect();
      $query->AddTable("compoentries");
      $query->AddWhere(sprintf_esc("compoid=%d",$_POST["compo"]));
      $query->AddOrder("playingorder");
      run_hook("admin_beamer_generate_compodisplay_dbquery",array("query"=>&$query));
      $entries = SQLLib::selectRows( $query->GetQuery() );

      $playingorder = 1;
      $out["result"]["entries"] = array();
      foreach ($entries as $t)
      {
        $a = array(
          "number" => $playingorder++,
          "title" => $t->title,
          "comment" => $t->comment,
        );
        if ($compo->showauthor)
        {
          $a["author"] = $t->author;
        }
        $out["result"]["entries"][] = $a;
      }
    } break;
    case "prizegiving":
    {
      $voter = SpawnVotingSystem();

      if (!$voter)
        die("VOTING SYSTEM ERROR");

      $compo = get_compo( $_POST["compo"] );

      $query = new SQLSelect();
      $query->AddTable("compoentries");
      $query->AddWhere(sprintf_esc("compoid=%d",$_POST["compo"]));
      run_hook("admin_beamer_generate_prizegiving_dbquery",array("query"=>&$query));
      $entries = SQLLib::selectRows( $query->GetQuery() );

      global $results;
      $results = array();
      $results = $voter->CreateResultsFromVotes( $compo, $entries );
      run_hook("voting_resultscreated_presort",array("results"=>&$results));
      arsort($results);
      $ranks = 0;

      run_hook("admin_beamer_prizegiving_rendervotes",array("results"=>&$results,"compo"=>$compo));

      $lastpoints = -1;
      foreach($results as $v)
      {
        if ($lastpoints != $v) $ranks++;
        $lastpoints = $v;
      }

      $lastpoints = -1;

      $out["result"]["componame"] = $compo->name;
      $rank = 0;
      $counter = 1;
      $out["result"]["results"] = array();
      foreach ($results as $k=>$t) {
        if ($lastpoints != (int)$t) $rank = $counter;
        $s = SQLLib::selectRow(sprintf_esc("select * from compoentries where id=%d",$k));

        $a = array(
          "ranking" => $rank,
          "points" => (int)$t,
          "title" => $s->title,
          "author" => $s->author,
        );
        array_unshift($out["result"]["results"],$a); // insert to start
        $lastpoints = $t;
        $counter++;
      }
    } break;
  }
  file_put_contents($DATAFILE,serialize($out));
  redirect("beamer.php");
}
printf("<h2>Change beamer setting</h2>\n");

$s = SQLLib::selectRows("select * from compos order by start");

printf("<p>Current mode: <a href='beamer.php?format=json'>%s</a></p>",_html($beamerData["result"]["mode"]));
?>

<div class='beamermode'>
<h3>Announcement</h3>
<form action="beamer.php" method="post" enctype="multipart/form-data">
  <textarea name="announcement"><?=_html(trim($beamerData["result"]["announcementhtml"]?:$beamerData["result"]["announcementtext"]))?></textarea><br/>
  <input type="checkbox" name="isHTML" id="isHTML" style='display:inline-block'<?=($beamerData["result"]["announcementhtml"]?" checked='checked'":"")?>/> <label for='isHTML'>Use HTML</label>
  <input type="hidden" name="mode" value="announcement"/>
  <input type="submit" value="Switch to Announcement mode."/>
</form>
</div>

<div class='beamermode'>
<h3>Compo countdown</h3>
<form action="beamer.php" method="post" enctype="multipart/form-data">
<select name="compo">
<?php
foreach($s as $t)
  printf("  <option value='%d'>%s</option>\n",$t->id,$t->name);
?>
</select><br/>
  <input type="hidden" name="mode" value="compocountdown"/>
  <input type="submit" value="Switch to Compo Countdown mode."/>
</form>
</div>

<div class='beamermode'>
<h3>Event countdown</h3>
<form action="beamer.php" method="post" enctype="multipart/form-data">
  <label for="eventname">Event name:</label>
  <input type="text" id="eventname" name="eventname" value="" required='yes'/>
  <label for="eventtime">Event time:</label>
  <input type="text" id="eventtime" name="eventtime" value="<?=date("Y-m-d H:i:s")?>"/>
  <input type="hidden" name="mode" value="compocountdown"/>
  <input type="submit" value="Switch to Event Countdown mode."/>
</form>
</div>

<div class='beamermode'>
<h3>Compo display</h3>
<form action="beamer.php" method="post" enctype="multipart/form-data">
<select name="compo">
<?php
foreach($s as $t)
  printf("  <option value='%d'>%s</option>\n",$t->id,$t->name);
?>
</select><br/>
  <input type="hidden" name="mode" value="compodisplay"/>
  <input type="submit" value="Switch to Compo Display mode."/>
</form>
</div>

<div class='beamermode'>
<h3>Prizegiving</h3>
<form action="beamer.php" method="post" enctype="multipart/form-data">
<select name="compo">
<?php
foreach($s as $t)
  printf("  <option value='%d'>%s</option>\n",$t->id,$t->name);
?>
</select><br/>
  <input type="hidden" name="mode" value="prizegiving"/>
  <input type="submit" value="Switch to Prizegiving mode."/>
</form>
</div>
<?php
include_once("footer.inc.php");
?>
