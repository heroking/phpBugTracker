<?php

include '../include.php';

page_open(array('sess' => 'usess', 'auth' => 'uauth', 'perm' => 'uperm'));

function do_form($versionid = 0) {
  global $q, $me, $projectid, $version, $active, $STRING;
  
  // Validation
  if (!$version = trim($version)) 
    $error = $STRING[giveversion];
  if ($error) { show_form($versionid, $error); return; }
  
  if (!$active) $active = 0;
  if (!$versionid) {
    $q->query("insert into Version (VersionID, ProjectID, Name, Active) values 
      (".$q->nextid('Version').", $projectid, '$version', '$active')");
  } else {
    $q->query("update Version set ProjectID=$projectid, Name='$version', 
      Active='$active' where VersionID = '$versionid'");
  }
  header("Location: project.php?op=edit&id=$projectid");
}  

function show_form($versionid = 0, $error = '') {
  global $q, $me, $t, $projectid, $version, $active, $TITLE;
  
  $t->set_file('content','versionform.html');
  if ($versionid && !$error) {
    $row = $q->grab("select * from Version where VersionID = '$versionid'");
    $t->set_var(array(
      'versionid' => $row[VersionID],
      'projectid' => $row[ProjectID],
      'project' => $q->grab_field("select Name from Project where 
        ProjectID = $row[ProjectID]"),
      'version' => $row[Name],
      'active' => $row[Active] ? 'checked' : '',
      'TITLE' => $TITLE[editversion]));
  } else {
    $t->set_var(array(
      'id' => $id,
      'me' => $me,
      'error' => $error,
      'versionid' => $versionid,
      'projectid' => $projectid,
      'project' => $q->grab_field("select Name from Project where 
        ProjectID = $projectid"),
      'version' => $version,
      'active' => $active ? ' checked' : '',
      'TITLE' => $id ? $TITLE[editversion] : $TITLE[addversion]));
  }
}

$t->set_file('wrap','wrap.html');

if ($op) switch($op) {
        case 'add' : show_form(); break;
        case 'edit' : show_form($id); break;
} elseif($submit) {     
        do_form($id);
} else list_items();

$t->pparse('main',array('content','wrap','main'));

page_close();

 ?>