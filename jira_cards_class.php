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

class jira_cards_class
{
    public string $WebpageName = "Sangoma Planning Poker";
    public string $version = "1.65";
    public string $room_number;
    public string $dataFile;
    public string $url;
    public bool $allPicked;
    private bool $logging = false; //Turn logging on and off
    public int $maxAttempts = 1000; // Maximum number of attempts to find an unused room number
    public array $GameDataInit = ['players' => [], 'modifier' => [], 'laser' => [], 'revealed' => false, 'firstPlayer' => null, 'firstIP' => null];
    public array $GameData;
    public string $full_url;

    //reveal screen data
    public int $average;
    public int $highest;
    public int $lowest;
    public int $mod_average;
    public int $mod_highest;
    public int $mod_lowest;
    public array $cardCounts = [];
    public array $selectedCards = [];
    public array $playNames = [];
    public string $js_cardValues;
    public string $js_playerNames;

    public function __construct($logging = false)
    {
        $this->logging = $logging;
        $ip_address = $_SERVER['REMOTE_ADDR'];

        if (!isset($_REQUEST['room_number'])) {
            // Clean up old game data files
            foreach (glob("game_*.json") as $file) {
                if (time() - filemtime($file) > 3600) {
                    unlink($file); // Delete old game data/log files
                }
            }

            //check to see if this IP address already has a room open
            //This will keep one machine from opening multiple rooms
            foreach (glob("GameData*.json") as $file) {
                $scanned_json = json_decode(file_get_contents($file), true);
                if ($scanned_json['firstIP'] === $ip_address) {
                    $_SESSION['name'] = $scanned_json['firstPlayer'];
                    [$name, $room_number] = explode("_", $file, 2);
                    $room_number = explode(".", $room_number);
                    $this->url = $_SERVER['PHP_SELF'] . "?room_number={$room_number[0]}";
                    header("Location: {$this->url}");
                    exit();
                }
            }

            //Create a new room
            $attempts = 0;
            do {
                $this->room_number = rand(1000, 1000 + $this->maxAttempts);
                $this->dataFile = "GameData_{$this->room_number}.json";
                $attempts++;

                // Break out if max attempts are reached
                if ($attempts >= $this->maxAttempts) {
                    $error = "Error: Unable to find an available room. Please try again later.";
                    $this->writeLog(array("error" => $error));
                    die($error);
                }
            } while (file_exists($this->dataFile));

            // Redirect with the new room number
            $this->GameData = $this->GameDataInit;
            //Mark the room with the creators IP address
            $this->GameData['firstIP'] = $ip_address;
            file_put_contents($this->dataFile, json_encode($this->GameData));
            $this->url = $_SERVER['PHP_SELF'] . "?room_number={$this->room_number}";
            header("Location: {$this->url}");
            exit();
        } else {
            $this->room_number = $_REQUEST['room_number'];
            $this->dataFile = "GameData_{$this->room_number}.json";
            if (!file_exists($this->dataFile)) {
                unset($_SESSION['room_number']);
                unset($_SESSION['name']);
                header("Location: index.php");
                exit();
            }
        }

        // Load game data, if it is more than one hour old, then reset it
        if (file_exists($this->dataFile) && (time() - filemtime($this->dataFile)) < 3600) {
            //Good data, just load it
            $this->GameData = json_decode(file_get_contents($this->dataFile), true);
        } else {
            //reset the game data
            $this->GameData = $this->GameDataInit;
            file_put_contents($this->dataFile, json_encode($this->GameData));
        }

        $_SESSION['room_number'] = $this->room_number;
        $this->url = $_SERVER['PHP_SELF'] . '?room_number=' . $this->room_number;
        $this->full_url = "<a href='" . $this->full_path() . "?room_number={$this->room_number}'>" . $this->full_path() . "?room_number={$this->room_number}</a>";

        // Ensure session name persists across resets
        if (!isset($_SESSION['name']) && isset($_REQUEST['name'])) {
            $_SESSION['name'] = $_REQUEST['name'];
        } else {
            //If a player is removed then unset their login upon the next refresh.
            if (isset($_SESSION['name']) && isset($this->GameData['resetPlayer']) && $this->GameData['resetPlayer'] === $_SESSION['name']) {
                unset($_SESSION['name']);
                unset($this->GameData['resetPlayer']);
                file_put_contents($this->dataFile, json_encode($this->GameData));
                session_unset();
                session_destroy();
                header("Location: index.php");
                exit();
            }
        }

        // Add player if not already in game
        if (isset($_SESSION['name']) && !array_key_exists($_SESSION['name'], $this->GameData['players'])) {
            $this->GameData['players'][$_SESSION['name']] = null;

            // Set first player if not already set
            if ($this->GameData['firstPlayer'] === null) {
                $this->GameData['firstPlayer'] = $_SESSION['name'];
                //Also Mark the room with the creators IP address
                $this->GameData['firstIP'] = $ip_address;
            }
            file_put_contents($this->dataFile, json_encode($this->GameData));
        }

        // work out if everyone has selected a card or not
        $this->allPicked = !in_array(null, $this->GameData['players'], true);
    }

    public function revealScreenCalcs(): void
    {
        $this->selectedCards = array_filter($this->GameData['players']);
        $cardValues = array_map('intval', $this->selectedCards);
        $this->playNames = [];

        if (count($cardValues) > 0) {
            $this->average = array_sum($cardValues) / count($cardValues);
            $this->average = $this->nearestFibonacci($this->average);
            $this->highest = max($cardValues);
            $this->lowest = min($cardValues);

            foreach ($cardValues as $value) {
                if (!isset($this->cardCounts[$value])) {
                    $this->cardCounts[$value] = 0;
                }
                $this->cardCounts[$value]++;
            }
            ksort($this->cardCounts, SORT_NUMERIC);
            $this->writeLog(array('cardCounts' => $this->cardCounts));
        }
        $this->js_cardValues = '[' . implode(',', $cardValues) . ']';

        $this->selectedCards = array();
        foreach ($this->GameData['players'] as $player => $card) {
            $modifier = $this->GameData['modifier'][$player];
            $this->writeLog(array('section' => 'before mod', 'card' => $card, 'modifier' => $modifier));
            if ($modifier !== 'Stand') $card = $this->getAdjacentFibonacci($card, $modifier);
            $this->writeLog(array('section' => 'after mod', 'card' => $card, 'modifier' => $modifier));
            $this->selectedCards[] = $card;

            if ($modifier === 'Lower') {
                $this->playNames[] = "↓" . $player;
            } elseif ($modifier === 'Higher') {
                $this->playNames[] = "↑" . $player;
            } else {
                $this->playNames[] = $player;
            }

        }
        $cardValues = array_map('intval', $this->selectedCards);
        $this->mod_average = intval(array_sum($cardValues) / count($cardValues));
        $this->mod_average = $this->nearestFibonacci($this->mod_average);
        $this->mod_highest = max($cardValues);
        $this->mod_lowest = min($cardValues);
        $this->js_playerNames = '["' . implode('","', $this->playNames) . '"]';
    }

    public function handleGameUpdates(): false|string
    {
        // Handle modifier selection modifier (only if logged in)
        //AJAX, requires return
        if (isset($_POST['modifier']) && isset($_SESSION['name'])) {
            $this->GameData['modifier'][$_SESSION['name']] = $_POST['modifier'];
            file_put_contents($this->dataFile, json_encode($this->GameData));
            return json_encode(array('success' => true));
        }

        // Handle card selection (only if logged in)
        //AJAX, requires return
        if (isset($_POST['card']) && isset($_SESSION['name'])) {
            $this->GameData['players'][$_SESSION['name']] = $_POST['card'];
            $this->GameData['modifier'][$_SESSION['name']] = 'Stand';
            file_put_contents($this->dataFile, json_encode($this->GameData));
            return json_encode(array('success' => true));
        }

        // Handle laser (only if logged in)
        //AJAX, requires return
        if (isset($_POST['addLaserVictim']) && isset($_SESSION['name'])) {
            $this->GameData['laser'][$_SESSION['name']] = $_POST['addLaserVictim'];
            file_put_contents($this->dataFile, json_encode($this->GameData));
            return json_encode(array('success' => true));
        }

        if (isset($_POST['removeLaserVictim']) && isset($_SESSION['name'])) {
            foreach ($this->GameData['laser'] as $whoFired => $atWho) {
                if ($atWho === $_POST['removeLaserVictim']) {
                    unset($this->GameData['laser'][$whoFired]);
                    file_put_contents($this->dataFile, json_encode($this->GameData));
                }
            }
            return json_encode(array('success' => true));
        }

        // Handle reveal (only first player can reveal)
        if (isset($_POST['reveal']) && $_SESSION['name'] === $this->GameData['firstPlayer']) {
            $this->GameData['revealed'] = true;
            file_put_contents($this->dataFile, json_encode($this->GameData));
        }

        // Handle un-reveal (only first player can reveal)
        if (isset($_POST['restart'])) {
            foreach ($this->GameData['players'] as $player => $card) {
                /** @var string $player */
                $this->GameData['players'][$player] = null;
                $this->GameData['modifier'][$player] = null;
            }
            $this->GameData['revealed'] = false;
            file_put_contents($this->dataFile, json_encode($this->GameData));
        }

        // Handle reset while keeping players or full reset
        if (isset($_POST['reset'])) {
            if (isset($_POST['fullReset'])) {
                $this->GameData = $this->GameDataInit;
            } else {
                foreach ($this->GameData['players'] as $player => $card) {
                    /** @var string $player */
                    $this->GameData['players'][$player] = null;
                    $this->GameData['modifier'][$player] = null;
                }
                $this->GameData['revealed'] = false;
            }
            file_put_contents($this->dataFile, json_encode($this->GameData));
            header("Location: {$this->url}");
        }

        // Handle player removal by first player
        if (isset($_SESSION['name']) && isset($_POST['removePlayer']) && $_SESSION['name'] === $this->GameData['firstPlayer']) {
            $playerToRemove = $_POST['removePlayer'];
            $this->writeLog(array('message' => "Running removePlayer [{$playerToRemove}]"));
            $this->writeLog(array('GameData' => $this->GameData));
            unset($this->GameData['players'][$playerToRemove]);
            $this->GameData['resetPlayer'] = $playerToRemove;
            $this->writeLog(array('GameData' => $this->GameData));
            file_put_contents($this->dataFile, json_encode($this->GameData));
        }

        // Handle player logout
        if (isset($_POST['logout']) && isset($_SESSION['name'])) {
            //if the controlling player logs out then the room is closed
            if ($_SESSION['name'] === $this->GameData['firstPlayer']) {
                $this->writeLog(array('LOGOUT firstPlayer' => $_SESSION['name']));
                unlink($this->dataFile);
                $this->GameData = [];
            } else {
                $this->writeLog(array('LOGOUT' => $_SESSION['name']));
                $playerToRemove = $_SESSION['name'];
                unset($this->GameData['players'][$playerToRemove]); // Remove user from game data
                unset($this->GameData['modifier'][$playerToRemove]); // Remove user from game data
            }
            if (!empty($this->GameData)) file_put_contents($this->dataFile, json_encode($this->GameData)); // Save updated game data
            session_unset(); // Clear session data
            session_destroy(); // Destroy session
            header("Location: index.php");
        }
        return 'continue';
    }

    public function getAdjacentFibonacci($num, $direction = 'Higher'): ?int
    {
        // Base Fibonacci sequence
        $fib = [1, 2]; // Assuming Fibonacci starts at 1,2,3,5,8,...
        $this->writeLog(array('section' => 'before fibonacci', 'num' => $num, 'direction' => $direction));
        // Generate Fibonacci sequence until the given number is found or exceeded
        while (end($fib) < $num + ($num - 1)) {
            $fib[] = $fib[count($fib) - 1] + $fib[count($fib) - 2];
        }
        $this->writeLog($fib);
        // Find the position of the given number
        $index = array_search($num, $fib);
        $this->writeLog(array('section' => 'after fibonacci', 'index' => $index));
        if ($index === false) {
            $this->writeLog(array('exit' => 'null'));
            return null; // Number is not in the Fibonacci sequence
        }

        // Return the next or previous Fibonacci number
        if ($direction === 'Higher') {
            $this->writeLog(array('result' => $fib[$index + 1]));
            return $fib[$index + 1] ?? null; // Next Fibonacci number
        } else {
            $this->writeLog(array('result' => $fib[$index - 1]));
            return $fib[$index - 1] ?? null; // Previous Fibonacci number
        }
    }

    // Function to find the nearest Fibonacci number
    public function nearestFibonacci($num): int
    {
        //$fib = [1, 2, 3, 5, 8, 13, 21, 34, 55];
        // Base Fibonacci sequence
        $fib = [1, 2]; // Assuming Fibonacci starts at 1,2,3,5,8,...

        // Generate Fibonacci sequence until the given number is found or exceeded
        while (end($fib) < $num + ($num - 1)) {
            $fib[] = $fib[count($fib) - 1] + $fib[count($fib) - 2];
        }

        $closest = $fib[0];
        foreach ($fib as $f) {
            if (abs($f - $num) < abs($closest - $num)) {
                $closest = $f;
            }
        }
        return $closest;
    }

    public function writeLog($data): void
    {
        if (!empty($data) && $this->logging && isset($_SESSION['room_number'])) {
            $room_number = $_SESSION['room_number'];
            $logFile = "game_log_{$room_number}.json";

            // Get the backtrace to find the calling file and line number
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            $lineNumber = $backtrace['line'] ?? 'unknown';
            $filename = $backtrace['file'] ?? 'unknown';

            // Add timestamp and line number to the log entry
            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s'), // Format: YYYY-MM-DD HH:MM:SS
                'file' => basename($filename),
                'line' => $lineNumber,
                'data' => $data
            ];

            $fp = fopen($logFile, 'a');
            fwrite($fp, json_encode($logEntry) . PHP_EOL);
            fclose($fp);
        }
    }

    public function full_path(): string
    {
        $s = &$_SERVER;
        $ssl = !empty($s['HTTPS']) && $s['HTTPS'] == 'on';
        $sp = strtolower($s['SERVER_PROTOCOL']);
        $protocol = substr($sp, 0, strpos($sp, '/')) . (($ssl) ? 's' : '');
        $port = $s['SERVER_PORT'];
        $port = ((!$ssl && $port == '80') || ($ssl && $port == '443')) ? '' : ':' . $port;
        $host = $s['HTTP_X_FORWARDED_HOST'] ?? ($s['HTTP_HOST'] ?? null);
        $host = $host ?? $s['SERVER_NAME'] . $port;
        $uri = $protocol . '://' . $host . $s['REQUEST_URI'];
        $segments = explode('?', $uri, 2);
        return $segments[0];
    }

    private function parse_template(string $tpl, array $vars): string
    {
        // Catch function tokens, handle if handler exists:
        $tpl = preg_replace_callback('~{{func:([a-z_]+)}}~', function ($match) {
            $func = 'handler_' . $match[1];
            //$this->writeLog(array('func' => $func));
            if (method_exists($this, $func)) {
                return $this->$func();
            }
            return "!!!What is: {$match[1]}!!!";
        }, $tpl);

        // Generate tokens for your variable keys;
        $keys = array_map(fn($key) => '{{' . $key . '}}', array_keys($vars));

        // Substitute tokens:
        return str_replace($keys, $vars, $tpl);
    }

    public function webHeader(): void
    {
        echo "<!DOCTYPE html><html lang='en'><head><title>{$this->WebpageName}</title>\n";
        echo "<link rel='stylesheet' href='jira_cards.css'></head>\n";
        echo "<body><div id='screenFlash'></div>";

        if(isset($_SESSION['name'])) {
            //This passes PHP data to the JS without the lag of AJAX
            echo "<form id='JSDATA'>\n";
            echo "<input type='hidden' name='room_number' value='{$_SESSION['room_number']}' />\n";
            $firstPlayerFlag = ($this->GameData['firstPlayer'] === $_SESSION['name']) ? 1 : 0;
            echo "<input type='hidden' name='firstPlayerFlag' value='{$firstPlayerFlag}' />\n";
            echo "<input type='hidden' name='name' value='{$_SESSION['name']}' />\n";
            echo "<input type='hidden' name='selectedCard' value='{$this->GameData['players'][$_SESSION['name']]}' />\n";
            $modifier = $this->GameData['modifier'][$_SESSION['name']] ?? "";
            echo "<input type='hidden' name='modifier' value='{$modifier}' />\n";
            echo "<input type='hidden' name='revealed' value='{$this->GameData['revealed']}' />\n";
            echo "<input type='hidden' name='dataFile' value='{$this->dataFile}' />\n";
            echo "</form>\n";
        }
    }

    public function webFooter(): void
    {
        if(isset($_SESSION['name'])) {
            echo "<script src='jira_cards.js'></script>\n";
        }
        echo "</body></html>\n";
    }

    public function webLogoffButton(): void
    {
        echo "<div style='position: absolute; top: 10px; right: 10px;'>";
        echo "<form method='post' name='LogoffForm' id='LogoffForm'>";
        echo "<button type='submit' name='logout'
                style='width: 100px; background: red; color: white; border: none; padding: 10px; cursor: pointer;'>
                Logout</button>";
        echo "</form></div>";

        echo "<div style='position: absolute; top: 50px; right: 10px;'>";
        echo "<button type='button' name='ForceRefresh'
                onclick='location.reload();'
                style='width: 100px; background: red; color: white; border: none; padding: 10px; cursor: pointer;'>
                Refresh</button>";
        echo "</div>";
    }

    public function webLogonForm(): void
    {
        echo "<form method='post' name='LogonForm' id='LogonForm'>";
        echo "<label for='name'>Enter your name:
                <input type='hidden' name='login' value='1' />
                <input type='text' name='name' required></label>
                <button type='submit'>Join</button>";
        echo "</form>";
    }

    public function handler_player_table(): string
    {
        $i = 0;
        $return = "";
        foreach ($this->GameData['players'] as $player => $card) {
            $i++;
            $player = htmlspecialchars($player);
            $return .= "<td style='text-align: left'>";
            //$this->writeLog(array('player_table_table'=>$this->GameData['players'],'session'=>$_SESSION['name'],'first_player'=>$this->GameData['firstPlayer']));
            if ($_SESSION['name'] === $this->GameData['firstPlayer']) {
                $return .= "<form method='post' style='display:inline;' id='{$player}Form'>
                            <input type='hidden' name='removePlayer' value='{$player}'>
                            <span id='{$player}Span' class='userButton' 
                            onclick=\"if (confirm('Are you sure you want to boot {$player}?')) { 
                                document.getElementById('{$player}Form').submit(); 
                            }\">{$player}</span>";
                $return .= empty($card) ? '' : "<img class='card_image' src='jira_cards_cardicon.png' height=20>";
                $return .= "</form>";
            } else {
                $return .= "<span id='{$player}Span' class='userButton'>{$player}</span>&nbsp;";
                $return .= empty($card) ? '' : "<img class='card_image' src='jira_cards_cardicon.png' height=20>";
            }
            $return .= "</td>";
            if ($i === 3) {
                $return .= "</tr><tr>";
                $i = 0;
            }
        }
        return $return;
    }

    public function handler_reveal_card_count_table(): string
    {
        $return = "";
        foreach ($this->cardCounts as $card => $count) {
            if ($count > 0) {
                $return .= "<tr><td style='text-align: left'>Card {$card} was chosen</td><td>{$count} times</td></tr>";
            }
        }
        return $return;
    }

    public function webWelcome(): void
    {
        $name = htmlspecialchars($_SESSION['name']);
        echo "<p>Welcome to room {$this->room_number}, {$name}!<br>Version {$this->version}</p>";
        if($_SESSION['name'] === $this->GameData['firstPlayer']) {
            echo "Give this URL to the other players {$this->full_url}&nbsp;<button onclick='copyToClipboard()'>Copy URL</button>";
        }
    }

    public function webRevealData(): void
    {
        $tpl = file_get_contents('jira_cardsHTML/revealData.tpl');
        $vars = array('StartAgain' => '',
            'average' => $this->average,
            'highest' => $this->highest,
            'lowest' => $this->lowest,
            'mod_average' => $this->mod_average,
            'mod_highest' => $this->mod_highest,
            'mod_lowest' => $this->mod_lowest,
            'js_cardValues' => $this->js_cardValues,
            'js_playerNames' => $this->js_playerNames);
        if ($_SESSION['name'] === $this->GameData['firstPlayer']) {
            $vars['StartAgain'] = "<form method='post'><button type='submit' name='restart'>Start Again</button></form>";
        }

        echo $this->parse_template($tpl, $vars);
    }

    private function revealCards(): string
    {
        return "<form method='post'><button type='submit' id='revealScores' name='reveal' style='visibility: hidden'> Reveal Cards </button></form>";
    }

    private function fullReset(): string
    {
        $return = "<button id='fireLaser' name='fire' onclick='fireLaser()' style='visibility: hidden'>Fire Attention Laser</button><br>";
        if ($_SESSION['name'] === $this->GameData['firstPlayer']) {
            $return .= "<form method='post' name='fullResetForm' id='fullResetForm'>
                        <button type='submit' name='reset'>Reset Game</button>
                        <label>
                          <input type='checkbox' name='fullReset'> Full Reset (Clear All Data)
                        </label>
                        </form>";
        }
        return $return;
    }

    public function webMainScreen(): void
    {
        $tpl = file_get_contents('jira_cardsHTML/mainScreen.tpl');
        $vars = array('form_revealCards' => $this->revealCards(),
            'form_fullReset' => $this->fullReset());
        if ($_SESSION['name'] === $this->GameData['firstPlayer']) {
            $vars['StartAgain'] = "<form method='post'><button type='submit' name='restart'>Start Again</button></form>";
        }

        echo $this->parse_template($tpl, $vars);
    }
}