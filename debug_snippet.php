<?php
// Add this temporary debugging code right after line 331 in index.php
// (after the input checkbox is echoed)

// Add this line to see what's being rendered:
echo '<td style="padding: 10px; text-align: center; background: ' . ($is_final_exam ? 'yellow' : 'white') . ';">';
echo '<input type="checkbox" class="category-checkbox" name="categoryids[]" value="' . $cat->id . '" ' . ($checkbox_checked ? 'checked' : '') . ' ' . $checkbox_disabled . ' onchange="alert(\'Checkbox changed! Disabled: \' + this.disabled + \', Checked: \' + this.checked);">';
echo '<br><small>disabled=' . ($is_final_exam ? 'true' : 'false') . '</small>';
echo '</td>';
