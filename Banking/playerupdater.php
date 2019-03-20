<html>

<head>
    <title>SHL Hockey -> Player Updater</title>
    {$headerinclude}

    <style>
        .bojoSection {
            margin-bottom: 20px; 
            border: 1px solid black; 
            border-radius: 2px;
            padding: 10px; 
            background: #f3f3f3; 
        }

        .bojoSection th,
        .bojoSection td {
            padding: 0px 10px;
            text-align: right;
        }

        .bojoSection th:nth-child(1),
        .bojoSection td:nth-child(1) {
            text-align: left;
        }

        .namesTable th,
        .namesTable td {
            padding: 0px 5px;
        }

        .namesTable th:nth-child(2),
        .namesTable td:nth-child(2) {
            width: 90px;
        }

        .namesTable input {
            width: 100%;
        }

        .bojoSection h4,
        .bojoSection h2 {
            margin-top: 0px;
            margin-bottom: 10px;
        }

        .negative {
            font-weight: bold;
        }
        .positive {
            font-weight: bold;
            color: #2ead30;
        }

        hr {
            margin-bottom: 20px;
        }

        .successSection {
            background: #d1f1cf;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid black;
        }

        .errorSection {
            background: #f0cfcf;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid black;
        }

        .successSection th,
        .successSection td {
            padding: 0px 10px;
            text-align: right;
        }

        .successSection th:nth-child(1),
        .successSection td:nth-child(1) {
            text-align: left;
        }

        .successSection h4 {
            margin: 0px;
            margin-bottom: 10px;
        }
        
        .nameCompare {
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid black;
        }

        .warning {
            background: yellow;
            font-weight: bold;
        }

        .success {
            background: #d1f1cf;
        }

    </style>
</head>

<body>
    {$header}

    <?php 

        include 'bankerOps.php';

        // Gets logged in user
        $myuid = $mybb->user['uid'];

        // if not logged in, go away why are you even here
        if ($myuid <= 0) { echo 'You are not logged in'; exit; }

        // Gets id of user from URL
        $uid = 0;
        if (isset($_GET["uid"]) && is_numeric($_GET["uid"])) {
            $uid = getSafeNumber($db, $_GET["uid"]);
        }
        else {
            // ... or redirects to your own page
            header('Location: http://simulationhockey.com/playerupdater.php?uid='.$myuid);
        }

        $isBanker = checkIfBanker($mybb);
        $isBanker = true; // TODO: remove for testing

        $curruser = getUser($db, $uid);
        $bankbalance = $curruser["bankbalance"];
        $currname = $curruser["username"];

        // If a submit button was pressed
        if (isset($mybb->input["bojopostkey"])) 
        {
            verify_post_check($mybb->input["bojopostkey"]);

            // If banker undid a transaction.
            if ($isBanker && isset($mybb->input["undotransaction"], $mybb->input["undoid"]) && is_numeric($mybb->input["undoid"]))
            {
                // Removes transaction row
                $transid = getSafeInputNum($db, $mybb, "undoid");
                $undoquery = $db->simple_select("banktransactions", "*", "id=$transid", array("limit" => 1));
                $undoresult = $db->fetch_array($undoquery);
                $undoamount = intval($undoresult["amount"]);
                $db->delete_query("banktransactions", "id=$transid");

                $bankbalance = updateBankBalance($db, $uid);

                displaySuccessTransaction($currname, $undoamount, $undoresult['title'], "Undo Transaction");
            }

            // If banker submitted a transaction.
            else if ($isBanker && isset($mybb->input["submittransaction"], $mybb->input["transactionamount"]))
            {
                $transAmount = getSafeInputNum($db, $mybb, "transactionamount");
                $transTitle = getSafeInputAlpNum($db, $mybb, "transactiontitle");
                $bankbalance = doTransaction($db, $transAmount, $transTitle, $uid, $myuid, $currname, "Banker Transaction");
            }

            // If a banker submitted a balance.
            else if ($isBanker && isset($mybb->input["submitbalance"], $mybb->input["balanceamount"]))
            {
                $transAmount = getSafeInputNum($db, $mybb, "balanceamount") - $bankbalance;
                $transTitle = "BALANCE AUDIT";
                $bankbalance = doTransaction($db, $transAmount, $transTitle, $uid, $myuid, $currname, "Banker Setting Balance");
            }

            // If the user submitted a transaction himself.
            else if (isset($mybb->input["submitpurchase"], $mybb->input["purchaseamount"]))
            {
                $transAmount = -abs(getSafeInputNum($db, $mybb, "purchaseamount"));
                $transTitle = getSafeInputAlpNum($db, $mybb, "purchasetitle");
                $bankbalance = doTransaction($db, $transAmount, $transTitle, $uid, $myuid, $currname, "User Purchase");
            }
        }
        ?>
        
        
        <!-- User Information -->
        <div class="bojoSection">
        <h2>{$currname}</h2>
        <table>
        <tr><th>Balance</th><td>
        <?php 
            if ($bankbalance < 0) { echo '-'; }
            echo "$" . number_format(abs($bankbalance), 0) . "</td></tr>";
        ?>
        </table>

        <hr />

        <h4>Bank Transactions</h4>
        <table>
        <tr>
        <th>Title</th>
        <th>Amount</th>
        <th>Date</th>
        <th>Made By</th>
        </tr>

        <?php 
            // Bank Transactions
            $transactionQuery = 
            "SELECT bt.*, banker.username AS 'owner'
                FROM mybb_banktransactions bt
                JOIN mybb_users banker ON bt.bankerid=banker.uid
                WHERE bt.uid=$uid
                ORDER BY bt.date DESC
                LIMIT 50";
            $bankRows = $db->query($transactionQuery);
            while ($row = $db->fetch_array($bankRows))
            {
                $date = new DateTime($row['date']);
                $transactionLink = '<a href="http://simulationhockey.com/banktransaction.php?id=' . $row['id'] . '">';
                $updateLink = '<a href="http://simulationhockey.com/playerupdater.php?uid=' . $row['bankerid'] . '">';
                $amountClass = ($row['amount'] < 0) ? 'negative' : 'positive';
                $negativeSign = ($row['amount'] < 0) ? '-' : '';

                echo '<tr>';
                echo '<td>' . $transactionLink . $row['title'] . '</a></td>';
                echo '<td class="' . $amountClass . '">' . $transactionLink . $negativeSign . '$' . number_format(abs($row['amount']), 0) . "</a></td>";
                echo "<td>" . $date->format('m/d/y') . "</td>";
                echo '<td>' . $updateLink . $row['owner'] . "</a></td>";
                if($isBanker)
                {
                    echo '<form method="post"><td><input type="submit" name="undotransaction" value="Undo" /></td>';
                    echo '<form method="post"><input type="hidden" name="undoid" value="'. $row['id'] .'" />';
                    echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" /></form>';
                }
                echo "</tr>";
            }
        ?>
        </table>
        </div>

        <!-- New Purchase: Only available to the actual user -->
        <if ($uid == $myuid) then>
            <div class="bojoSection">
            <h2>New Purchase</h2>
            <form method="post">
            <table>
            <tr><th>Amount</th><td><input type="number" name="purchaseamount" placeholder="Enter amount..." /></td></tr>
            <tr><th>Title</th><td><input type="text" name="purchasetitle" placeholder="Enter title..." /></td></tr>
            <tr><th></th><td><input type="submit" name="submitpurchase" value="Make Purchase" /></td></tr>
            <input type="hidden" name="bojopostkey" value="<?php echo $mybb->post_code ?>" />
            </table>
            </form>
            <p style="margin-bottom: 0px"><em>Write a postive number for a purchase transaction. Contact a banker if there\'s a mistake.</em></p>
            </div>
        </if>

        <!-- Add Banker Transaction -->
        <if ($isBanker) then>
            <div class="bojoSection">
            <h2>Banker Controls</h2>
            <h4>Add Transaction</h4>
            <form method="post">
            <table>
            <tr><th>Amount</th><td><input type="number" name="transactionamount" placeholder="Enter amount..." /></td></tr>
            <tr><th>Title</th><td><input type="text" name="transactiontitle" placeholder="Enter title..." /></td></tr>
            <tr><th></th><td><input type="submit" name="submittransaction" value="Add Transaction" /></td></tr>
            <input type="hidden" name="bojopostkey" value="<?php echo $mybb->post_code; ?>" />
            </table>
            <p style="margin-bottom: 0px"><em>Adds a transaction. If removing money, make sure to add the negative sign.</em></p>
            </form>
    
            <hr />
    
            <!-- Audit Balance -->
            <h4>Fix Balance</h4>
            <form method="post">
            <table>
            <tr><th>Amount</th><td><input type="number" name="balanceamount" placeholder="Enter balance..." /></td></tr>
            <tr><th></th><td><input type="submit" name="submitbalance" value="Set Balance" /></td></tr>
            <input type="hidden" name="bojopostkey" value="<?php echo $mybb->post_code; ?>" />
            </table>
            <p style="margin-bottom: 0px"><em>Sets the balance to the value, and adds a transaction to get it there.</em></p>
            </form>
            </div>
        </if>

        <?php $db->close; ?>

    {$boardstats}
    <br class="clear" />
    {$footer}
</body>

</html>