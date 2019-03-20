<html>

<head>
    <title>SHL Hockey -> Banker</title>
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

    // Gets current user logged in
    $myuid = $mybb->user['uid'];

    // if not logged in, go away why are you even here
    if ($myuid <= 0) { echo 'You are not logged in'; exit; }

    $isBanker = checkIfBanker($mybb);
    $isBanker = true; // TODO: remove for testing

    // If a submit button was pressed
    if (isset($mybb->input["bojopostkey"])) 
    {
        verify_post_check($mybb->input["bojopostkey"]);

        // Submitted list of names to search for
        if (isset($mybb->input["submitnames"]))
        {
            // keeps names in text box
            $namelist = trim($mybb->input["namelist"]);

            // Split by commas if present. otherwise split by new lines
            $charToSplit = (strpos($namelist, ',') !== false) ? "," : "\n";
            $namesArray = array_map('trim', explode($charToSplit, $namelist));

            for ($x = 0; $x < count($namesArray); $x++) 
                $namesArray[$x] = "'" . getEscapeString($db, $namesArray[$x]) . "'";

            $names = implode(",", $namesArray);

            // Gets list of users from db
            $nameRows = $db->simple_select("users", "*", "username in (" . $names . ")", array(
                "order_by" => 'username',
                "order_dir" => 'ASC'
            ));
        }

        // Submitted Mass Transactions
        else if (isset($mybb->input["submitmassseparate"]))
        {
            // keeps names in text box
            $namelist = trim($mybb->input["namelist"]);

            $isValid = true;

            $x = 0;
            $massinsert = array();
            while (isset($mybb->input["massid_" . $x]))
            {
                $currId = getSafeInputNum($db, $mybb, "massid_$x");
                $currAmount = getSafeInputNum($db, $mybb, "massamount_$x");
                $currTitle = getSafeInputAlpNum($db, $mybb, "masstitle_$x");

                if (strlen($currTitle) <= 0 || $currAmount == 0)
                {
                    $isValid = false;
                    break;
                }

                $massinsert[] = [
                    "uid" => $currId,
                    "bankerid" => $myuid,
                    "amount" => $currAmount,
                    "title" => $currTitle
                ];
                $x++;
            }

            if ($isValid)
            {
                // Adds rows to bank transactions
                $db->insert_query_multiple("banktransactions", $massinsert);

                $x = 0;
                while (isset($mybb->input["massid_" . $x]))
                {
                    // Updates user balances
                    $currId = getSafeInputNum($db, $mybb, "massid_" . $x++);
                    updateBankBalance($db, $currId);
                }

                echo '<div class="successSection">';
                echo '<h4>Success: Mass Update Results</h4>';
                echo '<table>';
                echo '<tr><th>User</th><th>User Id</th><th>Amount</th><th>Title</th></tr>';
                $x = 0;
                while (isset($mybb->input["massid_" . $x]))
                {
                    $currName = $mybb->input["massname_" . $x];
                    $currId = $mybb->input["massid_" . $x];
                    $currAmount = $mybb->input["massamount_" . $x];
                    $currTitle = $mybb->input["masstitle_" . $x];
        
                    echo '<td><a href="http://simulationhockey.com/playerupdater.php?uid=' . $currId . '">'.$currName.'</a></td>';
                    echo '<td>'.$currId.'</td><td>$'.$currAmount.'</td><td>'.$currTitle.'</td></tr>';
        
                    $x++;
                }
                echo '</table>';
                echo '</div>';
            }
            else
            {
                echo '<div class="errorSection">';
                echo '<h4>Error: There was invalid arguments for at least one of the transactions.</h4>';
                echo '</div>';
            }
        }
    }
    ?>

    <div class="bojoSection">
    <h2>Banker Controls</h2>
    <h4>Mass Update</h4>
    <small>submit a list of usernames separated by either commas or new lines.</small>
    <form onsubmit="return validateForms()" method="post">
    <textarea name="namelist" rows="8"><?php echo $namelist ?></textarea><br />
    <input type="submit" name="submitnames" value="Get Users" />
    <?php
    if($nameRows != NULL)
    {
        $nameCount = mysqli_num_rows($nameRows);
        $enteredCount = count($namesArray);
        if($nameCount > 0)
        {
            echo '<hr />';
            if ($nameCount != $enteredCount) {
                echo '<div class="nameCompare warning">';
            }
            else { echo '<div class="nameCompare success">'; }
            echo count($namesArray) . ' names entered<br/>' . $nameCount . ' names found';
            echo '</div>';
            echo '<table class="namesTable">';
            echo '<tr><th>username</th><th>amount</th><th>title</th></tr>';

            $massIndex = 0;
            while ($namerow = $db->fetch_array($nameRows))
            {
                echo "<tr><td>" . $namerow['username'] . "</td>";
                // echo "<td>" . $namerow['uid'] . "</td>";
                echo '<td><input type="number" id="massamount_' . $massIndex . '" name="massamount_' . $massIndex . '" value="0" /></td>';
                echo '<td><input type="text" id="masstitle_' . $massIndex . '" name="masstitle_' . $massIndex . '" /></td>';
                echo '<input type="hidden" name="massid_' . $massIndex . '" value="' . $namerow['uid'] . '" />';
                echo '<input type="hidden" name="massname_' . $massIndex . '" value="' . $namerow['username'] . '" />';
                if($massIndex === 0)
                {
                    echo '<td><input type="button" onclick="fillInUsers()" value="Fill the rest" /></td>';
                }
                echo "</tr>";
                $massIndex++;
            }
            echo '<tr><td colspan="3" style="height: 8px"></td></tr>';
            echo '<tr><td></td><td></td><td><input type="submit" name="submitmassseparate" value="Submit Transactions" /></td></tr>';
            echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" />';
            echo '</table>';
        }
    }
    echo '<input type="hidden" name="bojopostkey" value="' . $mybb->post_code . '" />';
    ?>
    </form>
    </div>

    <script>
    function fillInUsers() {
        var i = 0;
        var firstAmount = 0;
        var firstTitle = "";
        while(true) {
            var idAmount = "massamount_" + i;
            var idTitle = "masstitle_" + i;
            if (document.getElementById(idAmount) !== null) {
                if (i == 0) {
                    firstAmount = document.getElementById(idAmount).value;
                    firstTitle = document.getElementById(idTitle).value;
                }
                else {
                    document.getElementById(idAmount).value = firstAmount;
                    document.getElementById(idTitle).value = firstTitle;
                }
            }
            else {
                break;
            }
            i++;
        }
    }

    function validateForms() {
        var i = 0;
        while(true) {
            var idAmount = "massamount_" + i;
            var idTitle = "masstitle_" + i;
            if (document.getElementById(idAmount) !== null) {
                i++;
                var amount = document.getElementById(idAmount).value;
                var title = document.getElementById(idTitle).value;
                if (amount == 0)
                {
                    alert("A field has an invalid amount");
                    return false;
                }
                if (title.length <= 0)
                {
                    alert("A title is invalid");
                    return false;
                }
            }
            else { break; }

            if (i == 0)
            {
                alert("Where are the people?");
                return false;
            }
        }

        return true;
    }
    </script>

    <?php $db->close; ?>

    {$boardstats}
    <br class="clear" />
    {$footer}
</body>

</html>