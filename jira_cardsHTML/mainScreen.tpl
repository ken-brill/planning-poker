<div class="table">
    <h1>Pick a Card that represents your Story Point vote</h1>
    <div class="cards-container" id="cardsContainer">
        <!-- Cards will be generated here -->
    </div>
    <div class="info-container">
        <div class="left-container">
            <div class="label">
                <h2>Players</h2>
            </div>
            <div class="user-container" id="userContainer">
                <table class="scoreboard" id="scoreboard">
                    <tr>
                        {{func:player_table}}
                    </tr>
                </table>
            </div>
            <div class="reset">
                {{form_revealCards}}
            </div>
            <div class="reset">
                {{form_fullReset}}
            </div>
        </div>
        <div class="right-container">
            <div class="label"><h2>Selected Story Points</h2></div>
            <div class="selected-container" id="selectedContainer">
                <!-- Selected card will appear here -->
            </div>
        </div>
    </div>
</div>