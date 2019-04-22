<?php
function getAlpNum($inputText) {
    return trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $inputText));
}

function getSafeString($db, $inputText) {
    return trim($db->escape_string($inputText));
}

function getSafeNumber($db, $safeInput) {
    // $safeInput = getSafeString($db, $inputText);
    return intval($safeInput);
}

function getSafeAlpNum($db, $inputText) {
    $safeInput = getSafeString($db, $inputText);
    return getAlpNum($safeInput);
}

function checkIfBanker($mybb) {
    // Banker id is 13 as of me writing this.
    $groupstring = $mybb->user['usergroup'] . ',' . $mybb->user['additionalgroups'];
    $groups = explode(",", $groupstring);
    return in_array("13", $groups);
}

function getUser($db, $userId, $columns = "*") {
    // Gets user from DB.
    $userquery = $db->simple_select("users", $columns, "uid=$userId", array("limit" => 1));
    return $db->fetch_array($userquery);            
}

function getBankAccountLink($userid) {
    return 'http://simulationhockey.com/bankaccount.php?uid=' . $userid;
}

function getBankRequestLink($linkid) {
    return 'http://simulationhockey.com/bankrequest.php?id=' . $linkid;
}

function getBankTransactionLink($linkid) {
    return 'http://simulationhockey.com/banktransaction.php?id=' . $linkid;
}

function logAction($db, $title, $details) {
    $db->insert_query("banklogs", array("title" => $title, "details" => $details));
}

function updateBankBalance($db, $userId) {
    $balancequery = "SELECT sum(amount) AS sumamt FROM mybb_banktransactions WHERE uid=$userId";
    $banksumquery = $db->query($balancequery);
    $banksumresult = $db->fetch_array($banksumquery);
    if ($banksumresult != NULL) { $bankbalanceresult = intval($banksumresult['sumamt']); }
    else { $bankbalanceresult = 0; }
    $db->update_query("users", array("bankbalance" => $bankbalanceresult), "uid=$userId", 1);
    return $bankbalanceresult;
}

function getUserId($mybb) {
    return $mybb->user['uid'];
}

function addBankTransaction($db, $userId, $addAmount, $addTitle, $addDescription, $addcreatorId) {
    $addArray = [
        "uid" => $userId,
        "amount" => $addAmount,
        "title" => $addTitle,
        "createdbyuserid" => $addcreatorId
    ];

    if($addDescription != null) {
        $addArray["description"] = $addDescription;
    }

    $db->insert_query("banktransactions", $addArray);

    $newbankbalance = updateBankBalance($db, $userId);
    return $newbankbalance;
}

function addBankTransferRequest($db, $requestId, $reqtargetId, $reqamount, $reqtitle, $reqdescription) {
    if ($reqamount != 0 && strlen($reqtitle))
    {
        $addArray = [
            "userrequestid" => $requestId,
            "usertargetid" => $reqtargetId,
            "amount" => $reqamount,
            "title" => $reqtitle,
        ];
    
        if($reqdescription != null) {
            $addArray["description"] = $reqdescription;
        }
    
        $db->insert_query("banktransferrequests", $addArray);
        return true;
    }
    return false;
}

function doTransaction($db, $transAmount, $transTitle, $description, $userid, $creatorid, $username, $displayMessage) {
    if ($transAmount != 0 && strlen($transTitle) > 0)
    {
        $newbankbalance = addBankTransaction($db, $userid, $transAmount, $transTitle, $description, $creatorid);
        displaySuccessTransaction($username, $transAmount, $transTitle, $description, $displayMessage);
    }
    else {
        displayErrorTransaction();
        $newbankbalance = null;
    }

    return $newbankbalance;
}

function acceptTransferRequest($db, $uid, $bankerid, $approveid, $approverequester, $approvetarget, $approveamount, $approvetitle, $approvedescription) 
{
    // Sets transfer request to accepted
    $setapprovequery = "UPDATE mybb_banktransferrequests SET bankerapproverid=$bankerid, approvaldate=now() WHERE mybb_banktransferrequests.id=$approveid";
    $db->write_query($setapprovequery);

    // Gets Target User
    $targetUser = getUser($db, $approvetarget, "username");
    $targetname = $targetUser['username'];

    // Gets Approving Banker
    $requestUser = getUser($db, $approvetarget, "username");
    $requestname = $requestUser['username'];

    // Adds transactions and updates the balances
    $targetbalance = doTransaction($db, $approveamount, $approvetitle, $approvedescription, $approvetarget, $approverequester, $targetname, "Banker Approved Transfer - Target");
    $requestbalance = doTransaction($db, -$approveamount, $approvetitle, $approvedescription, $approverequester, $approverequester, $requestname, "Banker Approved Transfer - Requester");

    if ($uid == $approverequester) { return $requestbalance; }
    else if ($uid == $approvetarget) { return  $targetbalance; }
    return 0;
}

function displaySuccessTransaction($disName, $disAmount, $disTitle, $disDescription, $displayMessage) {
    echo '<div class="successSection">';
    echo "<h4>Success: $displayMessage</h4>";
    echo "<table>";
    echo '<tr><th>User</th><td>'.$disName.'</td></tr>';
    if($disAmount < 0) {
        echo '<tr><th>Amount</th><td>-$'.abs($disAmount).'</td></tr>';
    }
    else {
        echo '<tr><th>Amount</th><td>$'.$disAmount.'</td></tr>';
    }
    echo '<tr><th>Title</th><td>'.$disTitle.'</td></tr>';
    if($disDescription != null)
    {
        echo '<tr><th>Description</th><td>'.$disDescription.'</td></tr>';
    }
    echo "</table>";
    echo '</div>';
}

function displayErrorTransaction() {
    echo '<div class="errorSection">';
    echo "<h4>Error</h4>";
    echo '<p>Invalid arguments for the transaction</p>';
    echo '</div>';
}
?>