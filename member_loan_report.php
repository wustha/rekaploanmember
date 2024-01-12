<?php
/**
 *
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 * Modified for Excel output (C) 2010 by Wardiyono (wynerst@gmail.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

/* Library Member List */

// key to authenticate
define('INDEX_AUTH', '1');

// main system configuration
require '../../../../sysconfig.inc.php';
// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-reporting');
// start the session
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';
// privileges checking
$can_read = utility::havePrivilege('reporting', 'r');
$can_write = utility::havePrivilege('reporting', 'w');
 
if (!$can_read) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to access this area!').'</div>');
}

require SIMBIO.'simbio_GUI/template_parser/simbio_template_parser.inc.php';
require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_element.inc.php';
require SIMBIO.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require MDLBS.'reporting/report_dbgrid.inc.php';

$page_title = 'Rekap Peminjaman Anggota';
$reportView = false;
$num_recs_show = 50;
if (isset($_GET['reportView'])) {
    $reportView = true;
}

if (!$reportView) {
?>
    <!-- filter --><fieldset>
    <div class="per_title">
      <h2><?php echo __('Rekap Peminjaman Anggota'); ?></h2>
    </div>
    
    <div class="sub_section">
    <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="reportView">
    <div id="filterForm">
        <div class="divRow">
            <div class="divRowLabel"><?php echo __('Membership Type'); ?></div>
            <div class="divRowContent">
            <?php
            $mtype_q = $dbs->query('SELECT member_type_id, member_type_name FROM mst_member_type');
            $mtype_options = array();
            $mtype_options[] = array('0', __('ALL'));
            while ($mtype_d = $mtype_q->fetch_row()) {
                $mtype_options[] = array($mtype_d[0], $mtype_d[1]);
            }
            echo simbio_form_element::selectList('member_type', $mtype_options);
            ?>
            </div>
        </div>

        <div class="divRow">
            <div class="divRowLabel"><?php echo __('Loan Date From'); ?></div>
            <div class="divRowContent">
            <?php echo simbio_form_element::dateField('startDate', '2023-06-26'); ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo __('Loan Date Until'); ?></div>
            <div class="divRowContent">
            <?php echo simbio_form_element::dateField('untilDate', date('Y-m-d')); ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo __('Record each page'); ?></div>
            <div class="divRowContent"><input type="text" name="recsEachPage" size="3" maxlength="3" value="<?php echo $num_recs_show; ?>" /> <?php echo __('Set between 20 and 200'); ?></div>
        </div>
    </div>
    <div style="padding-top: 10px; clear: both;">
    <input type="button" name="moreFilter" class="btn btn-primary" value="<?php echo __('Show More Filter Options'); ?>" />
    <input type="submit" name="applyFilter" class="btn btn-primary" value="<?php echo __('Apply Filter'); ?>" />
    <input type="hidden" name="reportView" value="true" />
    </div>
    </form>
	</div>
    </fieldset>
    <!-- filter end -->
    
    <div class="dataListHeader" style="padding: 3px;"><span id="pagingBox"></span></div>
    <iframe name="reportView" id="reportView" src="<?php echo $_SERVER['PHP_SELF'].'?reportView=true'; ?>" frameborder="0" style="width: 100%; height: 500px;"></iframe>
<?php



} else {
    ob_start();
	
	
 // table spec
    $table_spec = 'loan AS l 
	LEFT JOIN member AS m ON m.member_id=l.member_id
	LEFT JOIN fines AS f ON f.member_id =f.member_id
		';

        


    // create datagrid
    $reportgrid = new report_datagrid();
		if (isset($_GET['startDate']) AND isset($_GET['untilDate'])) {
        $loan_filter = ' AND (TO_DAYS(l.loan_date) BETWEEN TO_DAYS(\''.$_GET['startDate'].'\') AND  TO_DAYS(\''.$_GET['untilDate'].'\'))';
        $fines_filter = ' AND (TO_DAYS(f.fines_date) BETWEEN TO_DAYS(\''.$_GET['startDate'].'\') AND TO_DAYS(\''.$_GET['untilDate'].'\'))';}
		else { 
		$fines_filter ='';
		$loan_filter ='';}
		$reportgrid->setSQLColumn(
		'm.member_id AS \''.__('Member ID').'\'',
		'm.member_name AS \''.__('Member Name').'\'',
		'round(COUNT(l.member_id)/395) AS \''.__('Jumlah Peminjaman').'\'',
		'(SELECT COUNT(return_date) FROM loan WHERE return_date!="NULL" AND return_date>due_date AND member_id=l.member_id '.$loan_filter.' ) AS \''.__('Overdue').'\'',
		'(SELECT SUM(renewed) FROM loan WHERE renewed>0 AND member_id=l.member_id '.$loan_filter.' ) AS \''.__('Perpanjangan').'\'',
		'(SELECT SUM(debet) FROM fines WHERE member_id=m.member_id '.$fines_filter.') AS \''.__('Fines').'\'',
		'(SELECT SUM(credit) FROM fines WHERE member_id=m.member_id '.$fines_filter.') AS \''.__('Credit').'\'');
		$reportgrid->setSQLorder('COUNT(l.member_id) DESC');
		$reportgrid->sql_group_by = 'm.member_id';

    // is there any search
    $criteria = 'm.member_id IS NOT NULL ';
    if (isset($_GET['member_type']) AND !empty($_GET['member_type'])) {
        $mtype = intval($_GET['member_type']);
        $criteria .= ' AND m.member_type_id='.$mtype;
    }
    if (isset($_GET['id_name']) AND !empty($_GET['id_name'])) {
        $id_name = $dbs->escape_string($_GET['id_name']);
        $criteria .= ' AND (m.member_id LIKE \'%'.$id_name.'%\' OR m.member_name LIKE \'%'.$id_name.'%\')';
    }

		//loan filter
	    if (isset($_GET['startDate']) AND isset($_GET['untilDate'])) {
        $criteria .= ' AND (TO_DAYS(loan_date) BETWEEN TO_DAYS(\''.$_GET['startDate'].'\') AND
        TO_DAYS(\''.$_GET['untilDate'].'\'))
						AND (TO_DAYS(fines_date) BETWEEN TO_DAYS(\''.$_GET['startDate'].'\') AND
        TO_DAYS(\''.$_GET['untilDate'].'\'))';}
	
    if (isset($_GET['recsEachPage'])) {
        $recsEachPage = (integer)$_GET['recsEachPage'];
        $num_recs_show = ($recsEachPage >= 20 && $recsEachPage <= 200)?$recsEachPage:$num_recs_show;
    }
    $reportgrid->setSQLCriteria($criteria);
    // put the result into variables
    echo $reportgrid->createDataGrid($dbs, $table_spec, $num_recs_show);

    echo '<script type="text/javascript">'."\n";
    echo 'parent.$(\'#pagingBox\').html(\''.str_replace(array("\n", "\r", "\t"), '', $reportgrid->paging_set).'\');'."\n";
    echo '</script>';
	

	
	$xlsquery = 'SELECT m.member_id AS \''.__('Member ID').'\''.
        ', m.member_name AS \''.__('Member Name').'\''.
        ', COUNT(loan_id)  FROM '.$table_spec.' WHERE '.$criteria
		.' GROUP BY l.member_id ORDER BY COUNT(loan_id)';
		

	unset($_SESSION['xlsdata']);
	$_SESSION['xlsquery'] = $xlsquery;
	$_SESSION['tblout'] = "member_list";

	//echo '<p><a href="../xlsoutput.php" class="button">'.__('Export to spreadsheet format').'</a></p>';
    $content = ob_get_clean();
    // include the page template
    require SB.'/admin/'.$sysconf['admin_template']['dir'].'/printed_page_tpl.php';
}?>
