<div style="width: 80%" class="table">
<div class="cards-container" id="cardsRevealContainer">
    <!-- Cards will be generated here -->
</div><br><br>
<table style=" margin-left: auto; margin-right: auto;">
    <tr>
        <td colspan="2" style="text-decoration: underline; text-align: left">Given Results:</td>
    </tr>
    <tr>
        <td style="text-align: left">Average of selected cards:</td>
        <td>{{average}}</td>
    </tr>
    <tr>
        <td style="text-align: left">Highest card chosen:</td>
        <td> {{highest}}</td>
    </tr>
    <tr>
        <td style="text-align: left">Lowest card chosen:</td>
        <td>{{lowest}}</td>
    </tr>
    <tr>
        <td colspan="2" style="text-decoration: underline; text-align: left"><br>Modified Results:</td>
    </tr>
    <tr>
        <td style="text-align: left">Average of selected cards:</td>
        <td> {{mod_average}}</td>
    </tr>
    <tr>
        <td style="text-align: left">Highest card chosen:</td>
        <td> {{mod_highest}}</td>
    </tr>
    <tr>
        <td style="text-align: left">Lowest card chosen:</td>
        <td> {{mod_lowest}}</td>
    </tr>
    <tr>
        <td style="text-decoration: underline; text-align: left"><br>Card Selection Counts:</td>
    </tr>
    {{func:reveal_card_count_table}}
</table>
</div>
{{StartAgain}}
<script>
    window.onload = function() {
        revealDeck({{js_cardValues}}, {{js_playerNames}});
        console.log("Page fully loaded");
    };
</script>