<html>

<head>
    <title>SHL Hockey -> Banker</title>
    {$headerinclude}
</head>

<body>
    {$header}

    <div class="bojoSection navigation">
    <h2>Banker Portal</h2>
    <p>At a glance view of active requests requiring banker decisions.</p>
    <p>Links: <a href="http://simulationhockey.com/bankmassupdate.php">Group Updates</a></p>
    </div>

    <?php 
    include 'bankerOps.php';

    $myuid = getUserId($mybb);

    // if not logged in, go away why are you even here
    if ($myuid <= 0) { echo 'You are not logged in'; exit; }

    $isBanker = checkIfBanker($mybb);
    // $isBanker = true; // TODO: For Testing

    // If a submit button was pressed
    if (isset($mybb->input["bojopostkey"])) 
    {
        verify_post_check($mybb->input["bojopostkey"]);

        // If banker approved a transfer.
        if ($isBanker && isset($mybb->input["approvetransfer"], $mybb->input["approveid"]))
        {
            $approveid = getSafeNumber($db, $mybb->input["approveid"]);
            $approvequery = $db->simple_select("banktransferrequests", "*", "id=$approveid", array("limit" => 1));
            $approveresult = $db->fetch_array($approvequery);
            $approveamount = intval($approveresult["amount"]);
            $approvetitle = $approveresult["title"];
            $approvedescription = $approveresult["description"];
            $approverequester = intval($approveresult["userrequestid"]);
            $approvetarget = intval($approveresult["usertargetid"]);

            $bankbalance = acceptTransferRequest($db, $uid, $myuid, $approveid, $approverequester, $approvetarget, $approveamount, $approvetitle, $approvedescription);
        }

        // If banker declined a transfer.
        else if ($isBanker && isset($mybb->input["declinetransfer"], $mybb->input["declineid"]))
        {
            $declineid = getSafeNumber($db, $mybb->input["declineid"]);
            
            $db->delete_query("banktransferrequests", "id=$declineid");

            echo '<div class="successSection">';
            echo '<h4>Successfully declined transaction</h4>';
            echo '</div>';
        }
    }
    ?>

    <div class="bojoSection navigation">
    <h2>Group Requests</h2>
    <h3>Pending Approval</h3>
    <?php 
        // Transfer Requests
        $transactionQuery = 
        "SELECT bt.*, urequester.username AS 'urequester'
            FROM mybb_banktransactiongroups bt
            LEFT JOIN mybb_users urequester ON bt.creatorid=urequester.uid
            WHERE bt.isapproved IS NULL
            ORDER BY bt.requestdate DESC";

        $bankRows = $db->query($transactionQuery);
        $bankRowCount = mysqli_num_rows($bankRows);

        if ($bankRowCount <= 0) {
            echo '<p>No active transfers</p>';
        }        
        else {
            echo 
            '<table>
            <tr>
            <th>Group Name</th>
            <th>Requester</th>
            <th>Date Requested</th>
            </tr>';

            while ($row = $db->fetch_array($bankRows))
            {
                $requestdate = new DateTime($row['datrequestdatee']);
                $requestdate = $requestdate->format('m/d/y');

                $ugroupLink = '<a href="http://simulationhockey.com/bankgrouptransaction.php?id=' . $row['id'] . '">';
                $urequesterLink = '<a href="http://simulationhockey.com/playerupdater.php?uid=' . $row['creatorid'] . '">';

                echo '<tr>';
                echo '<td>' . $ugroupLink . $row['groupname'] . '</a></td>';
                echo '<td>' . $urequesterLink . $row['urequester'] . '</a></td>';
                echo "<td>" . $requestdate . "</td>";
                echo '<td>' . $row['description'] . "</a></td>";
                echo "</tr>";
            }
            echo '</table>';
        }

    ?>
    
    <hr />

    <h3>Review History</h3>
    <?php 
        // Transfer Requests
        $transactionQuery = 
        "SELECT bt.*, urequester.username AS 'urequester', ubanker.username AS 'bankername'
            FROM mybb_banktransactiongroups bt
            LEFT JOIN mybb_users urequester ON bt.creatorid=urequester.uid
            LEFT JOIN mybb_users ubanker ON bt.bankerid=ubanker.uid
            WHERE bt.isapproved IS NOT NULL
            ORDER BY bt.requestdate DESC
            LIMIT 50";

        $bankRows = $db->query($transactionQuery);
        $bankRowCount = mysqli_num_rows($bankRows);

        if ($bankRowCount <= 0) {
            echo '<p>No transfers</p>';
        }        
        else {
            echo 
            '<table>
            <tr>
            <th>Group Name</th>
            <th>Requester</th>
            <th>Date Requested</th>
            <th>Approved?</th>
            <th>Banker</th>
            <th>Date Decided</th>
            </tr>';

            while ($row = $db->fetch_array($bankRows))
            {
                $decisiondate = new DateTime($row['decisiondate']);
                $decisiondate = $decisiondate->format('m/d/y');

                $requestdate = new DateTime($row['requestdate']);
                $requestdate = $requestdate->format('m/d/y');
                $requestApproval = intval($row['isapproved']) ? "Yes" : "No";

                $ugroupLink = '<a href="http://simulationhockey.com/bankgrouptransaction.php?id=' . $row['id'] . '">';
                $urequesterLink = '<a href="http://simulationhockey.com/playerupdater.php?uid=' . $row['creatorid'] . '">';

                echo '<tr>';
                echo '<td>' . $ugroupLink . $row['groupname'] . '</a></td>';
                echo '<td>' . $urequesterLink . $row['urequester'] . '</a></td>';
                echo "<td>" . $requestdate . "</td>";
                echo '<td>' . $requestApproval . "</td>";
                echo '<td>' . $row['bankername'] . "</td>";
                echo '<td>' . $decisiondate . "</td>";
                echo "</tr>";
            }
            echo '</table>';
        }

    ?>
    </div>

    <div class="bojoSection navigation">
    <h2>Active Transfer Requests</h2>
    <?php 
        // Transfer Requests
        $transactionQuery = 
        "SELECT bt.*, utarget.username AS 'utarget', urequester.username AS 'urequester'
            FROM mybb_banktransferrequests bt
            LEFT JOIN mybb_users urequester ON bt.userrequestid=urequester.uid
            LEFT JOIN mybb_users utarget ON bt.usertargetid=utarget.uid
            WHERE bt.bankerapproverid IS NULL
            ORDER BY bt.requestdate DESC
            LIMIT 50";

        $bankRows = $db->query($transactionQuery);
        $bankRowCount = mysqli_num_rows($bankRows);

        if ($bankRowCount <= 0) {
            echo '<p>No active transfers</p>';
        }        
        else {
            echo 
            '<table>
            <tr>
            <th>Title</th>
            <th>Requester</th>
            <th>Target</th>
            <th>Amount</th>
            <th>Date Requested</th>';
            if ($isBanker) { echo '<th></th><th></th>'; }
            echo '<th>Description</th>
            </tr>';

            while ($row = $db->fetch_array($bankRows))
            {
                $requestdate = new DateTime($row['datrequestdatee']);
                $requestdate = $requestdate->format('m/d/y');

                if($row['approvaldate'] === null) {
                    $approvedate = '';    
                } else {
                    $approvedate = new DateTime($row['approvaldate']);
                    $approvedate = $approvedate->format('m/d/y');
                }

                $urequesterLink = '<a href="http://simulationhockey.com/playerupdater.php?uid=' . $row['userrequestid'] . '">';
                $utargetLink = '<a href="http://simulationhockey.com/playerupdater.php?uid=' . $row['usertargetid'] . '">';
                $amountClass = ($row['amount'] < 0) ? 'negative' : 'positive';
                $negativeSign = ($row['amount'] < 0) ? '-' : '';

                echo '<tr>';
                echo '<td>' . $row['title'] . '</a></td>';
                echo '<td>' . $urequesterLink . $row['urequester'] . '</a></td>';
                echo '<td>' . $utargetLink . $row['utarget'] . '</a></td>';
                echo '<td class="' . $amountClass . '">' . $transactionLink . $negativeSign . '$' . number_format(abs($row['amount']), 0) . "</a></td>";
                echo "<td>" . $requestdate . "</td>";
                if($isBanker)
                {
                    if($row['bankerapproverid'] == null)
                    {
                        echo '<form method="post"><td><input type="submit" name="approvetransfer" value="Accept" /></td>';
                        echo '<input type="hidden" name="approveid" value="'. $row['id'] .'" />';
                        echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" /></form>';

                        echo '<form method="post"><td><input type="submit" name="declinetransfer" value="Decline" /></td>';
                        echo '<input type="hidden" name="declineid" value="'. $row['id'] .'" />';
                        echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" /></form>';
                    }
                    else { echo '<td></td>'; }
                }
                echo '<td>' . $row['description'] . "</a></td>";
                echo "</tr>";
            }
            echo '</table>';
        }

    ?>
    </div>

    <?php $db->close; ?>

    {$boardstats}
    <br class="clear" />
    {$footer}
</body>

</html>