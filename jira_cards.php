<?php
/**
 * Copyright 2025 Ken Brill kbrill@sangoma.com
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 * documentation files (the “Software”), to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and
 * to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of
 * the Software.
 *
 * THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO
 * THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
require_once "jira_cards_class.php";
session_start();
$jcc = new jira_cards_class(false);

//log the incoming request
if(count($_REQUEST)>1) {
    $jcc->writeLog($_REQUEST);
}

//Handle updates including AJAX calls
$response = $jcc->handleGameUpdates();
if($response !== 'continue') {
    echo $response;
    exit();
}

// Calculate statistics if revealed
if ($jcc->GameData['revealed']) {
    $jcc->revealScreenCalcs();
}

$jcc->webHeader();
$jcc->webLogoffButton();
if (!isset($_SESSION['name'])) {
    $jcc->webLogonForm();
} elseif ($jcc->GameData['revealed']) {
    $jcc->webRevealData();
} else {
    $jcc->webWelcome();
    $jcc->webMainScreen();
}
$jcc->webFooter();