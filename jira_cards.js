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
let cardSuits = [];
let lastModified = null;
let lastData = null;
let lastPlayersData = null;
let lastCardData = null;
let lastRevealedData = null;
let lastResetPlayer = null;
let numbers = [1, 2, 3, 5, 8, 13, 21, 34, 55];

//PHP to Javascript communication
const jsDataForm = document.getElementById('JSDATA');
let selectedCard = jsDataForm.elements['selectedCard'].value;
const name = jsDataForm.elements['name'].value;
const room_number = jsDataForm.elements['room_number'].value;
const firstPlayerFlag = jsDataForm.elements['firstPlayerFlag'].value;
const modifier = jsDataForm.elements['modifier'].value;
const revealed = jsDataForm.elements['revealed'].value;
const dataFile = jsDataForm.elements['dataFile'].value;

cardSuits[1] = 'heart';
cardSuits[2] = 'diamond';
cardSuits[3] = 'club';
cardSuits[5] = 'heart';
cardSuits[8] = 'spade';
cardSuits[13] = 'heart';
cardSuits[21] = 'club';
cardSuits[34] = 'spade';
cardSuits[55] = 'diamond';

const cardsContainer = document.getElementById("cardsContainer");
const cardsRevealContainer = document.getElementById("cardsRevealContainer");
const selectedContainer = document.getElementById("selectedContainer");
const fullUrl = window.location.href;

async function checkForUpdate() {
    if (dataFile !== null) {
        try {
            const response = await fetch(dataFile, {cache: "no-store"});
            const data = await response.json();
            const laserData = Object.values(data.laser);
            const cardData = data.players;
            const revealedData = data.revealed;
            const resetPlayer = data.resetPlayer;

            if (laserData.length > 0) {
                laserData.forEach(value => {
                    if (value === name) {
                        console.log("Found laser data entry - Flashing screen");
                        flashScreen();
                        //reset the server once this user has witnessed the
                        sendAttentionLaser(value, 'removeLaserVictim');
                    }
                });
            }
            lastRevealedData ??= revealedData;
            lastCardData ??= cardData;
            lastResetPlayer ??= resetPlayer;

            if (JSON.stringify(lastCardData) !== JSON.stringify(cardData)) {
                console.log("JSON file changed, updating scoreboard...");
                drawScoreboard(cardData);
            }
            if (JSON.stringify(lastRevealedData) !== JSON.stringify(revealedData) ||
                JSON.stringify(lastResetPlayer) !== JSON.stringify(resetPlayer)) {
                console.log("JSON file changed, refreshing...");
                location.reload();
            }

            lastRevealedData = revealedData;
            lastCardData = cardData;
            lastResetPlayer = resetPlayer;

            lastData = data;
        } catch (error) {
            console.error("Error checking JSON file:", error);
        }
    }
}

setInterval(checkForUpdate, 5000);

function drawScoreboard(cardData) {
    const container = document.getElementById('userContainer');
    if (!container) {
        console.error('No element with ID "userContainer" found.');
        return;
    }

    // Clear any existing table inside the container
    container.innerHTML = '';

    // Create a new table element
    const table = document.createElement('table');
    table.classList.add('scoreboard');
    table.id = 'scoreboard';

    // Create table body
    const tbody = document.createElement('tbody');

    let row;
    let count = 0;
    let hasNullScore = false; // Track if any player has a null score

    Object.entries(cardData).forEach(([name, score]) => {
        if (count % 3 === 0) {
            row = tbody.insertRow(); // Create a new row every 3 players
            // row = document.createElement('tr');
            // tbody.appendChild(row);
        }

        const cell = document.createElement('td');

        // Create the span
        const span = document.createElement('span');
        span.classList.add('userButton');
        span.id = `${name}span`;
        span.textContent = name;

        if (firstPlayerFlag === '1') {
            // Wrap the span in a form
            const form = document.createElement('form');
            form.id = `${name}form`;
            form.style.display = 'inline';
            form.method = 'POST';

            // Create hidden input field
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'removePlayer';
            hiddenInput.value = name;

            // Add click event to the span
            span.onclick = function () {
                if (confirm(`Are you sure you want to remove ${name}?`)) {
                    form.submit();
                }
            };

            // Append span and hidden input to the form
            form.appendChild(span);
            form.appendChild(hiddenInput);

            // Append form to the table cell
            cell.appendChild(form);
        } else {
            // Just add the span if firstPlayer is false
            cell.appendChild(span);
        }

        // If the player has a non-null score, add the image
        if (score !== null) {
            const img = document.createElement('img');
            img.classList.add('card_image');
            img.src = 'jira_cards_cardicon.png';
            img.height = 20;
            cell.appendChild(img);
        } else {
            hasNullScore = true; // Mark that at least one player has a null score
        }

        row.appendChild(cell);
        count++;
    });

    table.appendChild(tbody);

    // Append the new table to the container
    container.appendChild(table);

    // Find the revealScores button and update visibility based on conditions
    const revealScoresButton = document.getElementById('revealScores');
    if (revealScoresButton) {
        if (firstPlayerFlag === '1' && !hasNullScore) {
            revealScoresButton.style.visibility = 'visible'; // Show button if firstPlayer is true and no null scores
        } else {
            revealScoresButton.style.visibility = 'hidden'; // Hide button if conditions are not met
        }
    }

    // Find the fireLaser button and update visibility based on scores
    const fireLaserButton = document.getElementById('fireLaser');
    if (fireLaserButton) {
        fireLaserButton.style.visibility = hasNullScore ? 'visible' : 'hidden';
    }
}

function copyToClipboard() {
    navigator.clipboard.writeText(fullUrl).then(() => {
        alert("URL copied to clipboard!");
    }).catch(err => {
        console.error("Failed to copy: ", err);
    });
}

function isAlpha(text) {
    return text.length > 2;
}

function createCard(number, suit) {
    if(suit === undefined) {
        return  ;
    }
    const card = document.createElement("div");
    card.classList.add("card", suit.replace(/ /g, "_")); // Add both "card" class and suit-specific class

    // Create elements for the rank and suit
    const topLeft = document.createElement("div");
    topLeft.classList.add("rank", "top-left");
    topLeft.textContent = number;

    const bottomRight = document.createElement("div");
    bottomRight.classList.add("rank", "bottom-right");
    bottomRight.textContent = number;

    const suitSymbol = document.createElement("div");
    let suitText = getSuitSymbol(suit);
    if (isAlpha(suitText)) {
        suitSymbol.classList.add("player_name");
    } else {
        suitSymbol.classList.add("suit");
    }
    suitSymbol.textContent = getSuitSymbol(suit); // Get suit symbol dynamically

    // Append elements to the card
    card.appendChild(topLeft);
    card.appendChild(suitSymbol);
    card.appendChild(bottomRight);

    // Add click event
    card.onclick = function () {
        selectCard(number, suit);
    };

    return card;
}

// Function to return the correct suit symbol
function getSuitSymbol(suit) {
    const suits = {
        heart: "♥",
        diamond: "♦",
        spade: "♠",
        club: "♣",
    };
    return suits[suit] || suit; // Default to the string if suit is unknown
}

function selectCard(pickedCard, suit) {
    const originalCard = [...cardsContainer.children].find(card => card.textContent.includes(pickedCard));
    if (!originalCard) return;

    // Clone the card and get its position
    const rect = originalCard.getBoundingClientRect();
    const clone = originalCard.cloneNode(true);
    document.body.appendChild(clone);

    // Remove the picked card from cardsContainer *immediately*
    numbers = numbers.filter(num => num !== pickedCard);
    //if this is the first card selected then selectedCard will be invalid
    if (selectedCard > 0) {
        numbers = [...new Set([...numbers, Number(selectedCard)])];
    }
    numbers.sort((a, b) => a - b);
    updateDeck(); // Prioritize updating the deck immediately

    // Position the clone where the original card was
    clone.style.position = "absolute";
    clone.style.left = `${rect.left}px`;
    clone.style.top = `${rect.top}px`;
    clone.style.width = `${rect.width}px`;
    clone.style.height = `${rect.height}px`;
    clone.style.transition = "all 0.5s ease-in-out";
    clone.style.zIndex = "1000";

    // Get destination position
    const selectedRect = selectedContainer.getBoundingClientRect();

    setTimeout(() => {
        clone.style.left = `${selectedRect.left + selectedContainer.clientWidth / 2 - rect.width / 2}px`;
        clone.style.top = `${selectedRect.top}px`;
        clone.style.transform = "scale(1.2)";
    }, 50);

    setTimeout(() => {
        document.body.removeChild(clone);
        selectedCard = pickedCard;
        updateSelected(); // Update selectedContainer ASAP
        sendCardSelection(pickedCard);
    }, 500);
}

function updateDeck() {
    cardsContainer.innerHTML = "";
    numbers.forEach(num => {
        let test = createCard(num, cardSuits[num])
        cardsContainer.appendChild(test);
    });
}

function revealDeck(selectedCards, playerNames) {
    cardsRevealContainer.innerHTML = "";
    let index = 0;
    selectedCards.forEach(num => {
        cardsRevealContainer.appendChild(createCard(num, playerNames[index]));
        index++;
    });
}

function updateSelected() {
    if (selectedCard) {
        selectedContainer.innerHTML = "";
        const selectedElement = createCard(selectedCard, cardSuits[selectedCard]);
        //Set the default modifier if a new card is selected
        // Create a wrapper div for better layout
        const selectionWrapper = document.createElement("div");
        selectionWrapper.style.display = "flex";
        selectionWrapper.style.flexDirection = "column"; // Stack items vertically
        selectionWrapper.style.alignItems = "center"; // Center-align the items
        selectionWrapper.style.marginTop = "15px";

        // Create a container for selection options
        const selectionOptions = document.createElement("div");
        selectionOptions.style.marginTop = "10px";
        selectionOptions.style.display = "flex";
        selectionOptions.style.gap = "10px"; // Add space between buttons

        // Create "Higher" button
        const higherButton = document.createElement("button");
        higherButton.textContent = "↑";
        higherButton.name = 'higher';
        higherButton.title = "I could go higher";
        higherButton.onclick = () => indicateChoice("Higher");
        if (modifier === 'Higher') {
            higherButton.style.backgroundColor = 'lightblue';
        }
        if (selectedCard === 55) {
            higherButton.style.display = 'none';
        }

        // Create "Stand" button
        const standButton = document.createElement("button");
        standButton.textContent = "=";
        standButton.name = 'stand';
        standButton.title = "I think I'll stand";
        standButton.onclick = () => indicateChoice("Stand");
        if (modifier === 'Stand') {
            standButton.style.backgroundColor = 'lightblue';
        }

        // Create "Lower" button
        const lowerButton = document.createElement("button");
        lowerButton.textContent = "↓";
        lowerButton.name = 'lower';
        lowerButton.title = "I could go lower";
        lowerButton.onclick = () => indicateChoice("Lower");
        if (modifier === 'Lower') {
            lowerButton.style.backgroundColor = 'lightblue';
        }

        if (selectedCard === 1) {
            lowerButton.style.display = 'none';
        }

        // Append buttons to the selection options container
        selectionOptions.appendChild(lowerButton);
        selectionOptions.appendChild(standButton);
        selectionOptions.appendChild(higherButton);

        // Append selected card and options to the wrapper
        selectionWrapper.appendChild(selectedElement);
        selectionWrapper.appendChild(selectionOptions);

        // Append wrapper to the selected container
        selectedContainer.appendChild(selectionWrapper);
    }
}

async function sendAttentionLaser(who, jobName) {
    const formData = new FormData();
    formData.append(jobName, who);

    try {
        const response = await fetch(fullUrl, {
            method: "POST",
            // Set the FormData instance as the request body
            body: formData,
        });
        console.log(await response.json());
    } catch (e) {
        console.error(e);
    }
}

// Function to handle Higher/Lower selection
async function indicateChoice(choice) {
    switch (choice) {
        case 'Stand':
            document.querySelector('button[name="stand"]').style.backgroundColor = "lightblue";
            document.querySelector('button[name="higher"]').style.backgroundColor = "white";
            document.querySelector('button[name="lower"]').style.backgroundColor = "white";
            break;
        case 'Lower':
            document.querySelector('button[name="stand"]').style.backgroundColor = "white";
            document.querySelector('button[name="higher"]').style.backgroundColor = "white";
            document.querySelector('button[name="lower"]').style.backgroundColor = "lightblue";
            break;
        case 'Higher':
            document.querySelector('button[name="stand"]').style.backgroundColor = "white";
            document.querySelector('button[name="higher"]').style.backgroundColor = "lightblue";
            document.querySelector('button[name="lower"]').style.backgroundColor = "white";
            break;
        default:
            document.querySelector('button[name="stand"]').style.backgroundColor = "white";
            document.querySelector('button[name="higher"]').style.backgroundColor = "white";
            document.querySelector('button[name="lower"]').style.backgroundColor = "white";
    }
    const formData = new FormData();
    formData.append("modifier", choice);

    try {
        const response = await fetch(fullUrl, {
            method: "POST",
            // Set the FormData instance as the request body
            body: formData,
        });
        console.log(await response.json());
    } catch (e) {
        console.error(e);
    }
}

async function sendCardSelection(cardNumber) {
    const formData = new FormData();
    formData.append("card", cardNumber);

    try {
        const response = await fetch(fullUrl, {
            method: "POST",
            // Set the FormData instance as the request body
            body: formData,
        });
        console.log(await response.json());
    } catch (e) {
        console.error(e);
    }
}

// Function to play explosion sound
function playExplosionSound() {
    const explosionSound = new Audio('explosion-312361.mp3');
    explosionSound.play();  // Play the explosion sound
}

function resetFireButton() {
    const fireButton = document.getElementById('fireLaser');
    fireButton.disabled = false;
}

function fireLaser() {
    //Disable the button to limit fire frequency
    const fireButton = document.getElementById('fireLaser');
    fireButton.disabled = true;

    const timerId = setTimeout(resetFireButton, 5000);
    const table = document.querySelector('.table');
    const scoreboard = document.querySelector('.scoreboard');
    const userContainer = document.getElementById('userContainer');

    if (!table || !userContainer || !scoreboard) return;

    // Find all <td> elements that do NOT contain an <img>
    const spans = [...scoreboard.querySelectorAll('td')].filter(td =>
        !td.querySelector('img') // Exclude cells that contain an <img>
    );

    if (spans.length === 0) return; // No valid targets

    // Select a random target
    const target = spans[Math.floor(Math.random() * spans.length)];
    const targetRect = target.getBoundingClientRect();

    //Send the victims name back to the server
    const targetName = target.outerText;
    sendAttentionLaser(targetName, 'addLaserVictim');

    // Get .table position
    const tableRect = table.getBoundingClientRect();
    const corners = [
        {x: tableRect.left, y: tableRect.top}, // Top-left
        {x: tableRect.right, y: tableRect.top}, // Top-right
        {x: tableRect.left, y: tableRect.bottom}, // Bottom-left
        {x: tableRect.right, y: tableRect.bottom} // Bottom-right
    ];

    // Pick a random firing corner
    const start = corners[Math.floor(Math.random() * corners.length)];
    const endX = targetRect.left + targetRect.width / 2;
    const endY = targetRect.top + targetRect.height / 2;

    // Calculate laser angle and length
    const deltaX = endX - start.x;
    const deltaY = endY - start.y;
    const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
    const angle = Math.atan2(deltaY, deltaX) * (180 / Math.PI);

    // Create laser element (thin line)
    const laser = document.createElement('div');
    laser.style.position = 'absolute';
    laser.style.width = `${distance}px`;
    laser.style.height = '3px';
    laser.style.background = 'red';
    laser.style.boxShadow = '0 0 10px rgba(255, 0, 0, 0.8)';
    laser.style.transformOrigin = 'left center';
    laser.style.transform = `rotate(${angle}deg)`;
    laser.style.left = `${start.x}px`;
    laser.style.top = `${start.y}px`;
    laser.style.zIndex = '1000';
    document.body.appendChild(laser);

    // Play the explosion sound
    playExplosionSound();

    // Animate the laser line
    laser.animate([
        {width: '0px', opacity: 1},
        {width: `${distance}px`, opacity: 1}
    ], {
        duration: 300,
        easing: 'linear',
        fill: 'forwards'
    });

    // Explosion effect + Shake scoreboard
    setTimeout(() => {
        document.body.removeChild(laser);

        // Explosion effect using image
        const explosion = document.createElement('img');
        explosion.src = 'https://www.kindpng.com/picc/m/5-54003_transparent-background-cartoon-explosion-hd-png-download.png';
        explosion.style.position = 'absolute';
        explosion.style.left = `${endX - 25}px`;
        explosion.style.top = `${endY - 25}px`;
        explosion.style.width = '50px';
        explosion.style.height = '50px';
        explosion.style.zIndex = '1000';
        explosion.style.pointerEvents = 'none';
        explosion.style.mixBlendMode = 'multiply';
        document.body.appendChild(explosion);

        // 🔥 Shake the scoreboard table
        scoreboard.classList.add('shake');

        // Remove explosion & shake class after animation
        setTimeout(() => {
            document.body.removeChild(explosion);
            scoreboard.classList.remove('shake');
        }, 500);
    }, 300);
}

function flashScreen() {
    const flash = document.getElementById("screenFlash");
    playExplosionSound();
    // Make the flash visible
    flash.style.opacity = "1";

    // Fade out after a short delay
    setTimeout(() => {
        flash.style.opacity = "0";
    }, 100); // Adjust duration if needed
}

if (revealed !== 'true') {
    if (selectedCard !== null) {
        numbers = numbers.filter(num => num !== selectedCard);
    }
    updateDeck();
    updateSelected();
}