<?php

// query.php - Query the bug database

include 'include.php';

page_open(array('sess' => 'usess', 'auth' => 'uauth'));

function show_query() {
  global $q, $t, $status, $resolution, $os, $priority, $severity, $TITLE;
  
  $nq = new dbclass;
  
  $t->set_file('content','queryform.html');
  
  // Build the javascript-powered select boxes
  $q->query("select ProjectID, Name from Project where Active order by Name");
  while (list($pid, $pname) = $q->grab()) {
    // Version array
    $js .= "versions['$pname'] = new Array(new Array('','All'),";
    $nq->query("select Name, VersionID from Version where ProjectID = $pid and Active");
    while (list($version,$vid) = $nq->grab()) {
      $js .= "new Array($vid,'$version'),";
    }
    if (substr($js,-1) == ',') $js = substr($js,0,-1);
    $js .= ");\n";
    
    // Component array
    $js .= "components['$pname'] = new Array(new Array('','All'),";
    $nq->query("select Name, ComponentID from Component where ProjectID = $pid and Active");
    while (list($comp,$cid) = $nq->grab()) {
      $js .= "new Array($cid,'$comp'),";
    }
    if (substr($js,-1) == ',') $js = substr($js,0,-1);
    $js .= ");\n";
  }
  
  $t->set_var(array(
    'js' => $js,
    'status' => build_select('Status',$q->grab_field("select StatusID from Status
      where Name = 'New'")),
    'resolution' => build_select('Resolution',$resolution),
    'os' => build_select('OS',-1), // Prevent the OS regex selection
    'priority' => build_select('priority',$priority),
    'severity' => build_select('Severity',$severity),
    'projects' => build_select('Project'),
    'TITLE' => $TITLE[bugquery]
    ));
      
}

function build_query() {
  global $q, $sess, $querystring, $status, $resolution, $os, $priority, 
    $severity, $email1, $emailtype1, $emailfield1, $Title, $Description, $URL, 
    $Title_type, $Description_type, $URL_type, $projects, $versions, $components;

  // Select boxes
  if ($status) $flags[] = 'Status in ('.join(',',$status).')';
  if ($resolution) $flags[] = 'Resolution in ('.join(',',$resolution).')';
  if ($os) $flags[] = 'OS in ('.join(',',$os).')';
  if ($priority) $flags[] = 'Priority in ('.join(',',$priority).')';
  if ($severity) $flags[] = 'Severity in ('.join(',',$severity).')';
  if ($flags) $query[] = '('.join(' or ',$flags).')';
  
  // Email field(s)
  if ($email1) {
    switch($emailtype1) {
      case 'like' : $econd = "like '%$email1%'"; break;
      case 'rlike' : 
      case 'not rlike' : 
      case '=' : $econd = "$emailtype1 '$email1'"; break;
    }
    foreach($emailfield1 as $field) $equery[] = "$field.Email $econd";
    $query[] = '('.join(' or ',$equery).')';
  }
  
  // Text search field(s)
  foreach(array('Title','Description','URL') as $searchfield) {
    if ($$searchfield) {
      switch (${$searchfield."_type"}) {
        case 'like' : $cond = "like '%".$$searchfield."%'"; break;
        case 'rlike' : $cond = "rlike '".$$searchfield."'"; break;
        case 'not rlike' :$cond = "not rlike '".$$searchfield."'"; break;
      }
      $fields[] = "$searchfield $cond";
    }
  }
  if ($fields) $query[] = '('.join(' or ',$fields).')';
  
  // Project/Version/Component
  if ($projects) {
    $proj[] = "Bug.Project = $projects";
    if ($versions) $proj[] = "Bug.Version = $versions";
    if ($components) $proj[] = "Component = $components";
    $query[] = '('.join(' and ',$proj).')';
  }
  
  if ($query) $querystring = join (' and ',$query);
  if (!$sess->is_registered('querystring')) $sess->register('querystring');
}

function list_items() {
  global $querystring, $me, $q, $t, $selrange, $order, $sort, $query, 
    $page, $op, $select, $TITLE, $STRING;
  
  $t->set_file('content','buglist.html');
  $t->set_block('content','row','rows');
  
  if (!$order) { $order = 'BugID'; $sort = 'asc'; }
  if (!$querystring or $op == 'doquery') build_query();
  $nr = $q->grab_field("select count(*) from Bug left join User Owner on 
    Bug.AssignedTo = Owner.UserID left join User Reporter on 
    Bug.CreatedBy = Reporter.UserID ".($querystring != '' ? "where $querystring": ''));

  list($selrange, $llimit, $npages, $pages) = multipages($nr,$page,
    "order=$order&sort=$sort");
                
  $t->set_var(array(
    'pages' => $pages,
    'first' => $llimit+1,
    'last' => $llimit+$selrange > $nr ? $nr : $llimit+$selrange,
    'total' => $nr,
    'project' => build_select('Project'),
    'TITLE' => $TITLE[buglist]));
  
  $q->query("select BugID, Title, Reporter.Email as Reporter, Owner.Email as Owner, 
    Severity.Name as Severity, Bug.CreatedDate, Status.Name as Status, 
    Priority, Version.Name as Version, Component.Name as Component, 
    Resolution.Name as Resolution from Bug, Severity, Status, Version, 
    Component left join User Owner on Bug.AssignedTo = Owner.UserID 
    left join User Reporter on Bug.CreatedBy = Reporter.UserID 
    left join Resolution on Bug.Resolution = ResolutionID 
    where Severity = SeverityID and Status = StatusID and 
    Bug.Version = VersionID and Component = ComponentID ".
    ($querystring != '' ? "and $querystring " : '').
    "order by $order $sort limit $llimit, $selrange");
        
  $headers = array(
    'bugid' => 'BugID',
    'title' => 'Title',
    'description' => 'Description',
    'url' => 'URL',
    'severity' => 'Severity.SortOrder',
    'priority' => 'Priority',
    'status' => 'Status.SortOrder',
    'owner' => 'Owner',
    'createdby' => 'Reporter',
    'createddate' => 'CreatedDate',
    'project' => 'Project',
    'component' => 'Component',
    'os' => 'OS',
    'browserstring' => 'BrowserString',
    'resolution' => 'Resolution');

  sorting_headers($me, $headers, $order, $sort, "page=$page");
        
  if (!$q->num_rows()) {
    $t->set_var('rows',"<tr><td>$STRING[nobugs]</td></tr>");
    return;
  }

  while ($row = $q->grab()) {
    $t->set_var(array(
      'bgcolor' => (++$i % 2 == 0) ? '#dddddd' : '#ffffff',
      'bugid' => $row[BugID],
      'title' => $row[Title],
      'description' => $row[Description],
      'url' => $row[URL],
      'severity' => $row[Severity],
      'priority' => $select['priority'][$row[Priority]],
      'status' => $row[Status],
      'assignedto' => $row[AssignedTo],
      'reporter' => $row[Reporter],
      'owner' => $row[Owner],
      'createddate' => date(DATEFORMAT,$row[CreatedDate]),
      'project' => $row[Project],
      'component' => $row[Component],
      'os' => $row[OS],
      'browserstring' => $row[BrowserString],
      'resolution' => $row[Resolution]));
    $t->parse('rows','row',true);
  }
}

$t->set_file('wrap','wrap.html');

if ($op == 'query') show_query();
else list_items();

$t->pparse('main',array('content','wrap','main'));

page_close();

?>