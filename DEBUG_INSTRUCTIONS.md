# DEBUGGING INSTRUCTIONS

Add these console.log statements to your index.php file to debug the checkbox issue:

## At line 236 (right after 'function updateIncludeCheckboxes() {'), add:
echo '    console.log("=== updateIncludeCheckboxes called ===");';

## At line 237 (right after 'var checkboxes = document.querySelectorAll...'), add:
echo '    console.log("Found checkboxes:", checkboxes.length);';

## At line 242 (right after 'var shouldDisable = rowRadio && rowRadio.checked;'), add:
echo '        console.log("Checkbox value:", checkbox.value, "shouldDisable:", shouldDisable, "radio:", rowRadio ? rowRadio.checked : "none");';

## At line 245 (right after 'if (shouldDisable) {'), add:
echo '            console.log("DISABLING checkbox:", checkbox.value);';

## At line 252 (right after '} else {'), add:
echo '            console.log("ENABLING checkbox:", checkbox.value);';

## At line 262 (right after 'document.addEventListener("DOMContentLoaded", function() {'), add:
echo '    console.log("=== DOM Ready ===");';

## At line 278 (inside the click handler, right after 'checkbox.addEventListener("click", function(e) {'), add:
echo '            console.log("CLICK on checkbox:", this.value, "disabled?", this.disabled);';

## After making these changes:
1. Save the file
2. Reload the page in your browser
3. Open Developer Tools (F12)
4. Go to the Console tab
5. Try clicking on a checkbox that should be disabled
6. Send me the console output