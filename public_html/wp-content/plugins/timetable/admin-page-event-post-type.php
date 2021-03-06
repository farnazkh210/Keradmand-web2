<div class="wrap timetable_settings_section first">
	<h2><?php esc_html_e("Events post type configuration", "timetable"); ?></h2>
</div>
<form action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" method="post" id="timetable_events_settings">
	<div>
		<table class="timetable_table form-table">
			<tr valign="top">
				<th>
					<label for="timetable_events_settings_slug"><?php esc_html_e("Event slug: ", "timetable"); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="timetable_events_settings_slug" id="timetable_events_settings_slug" value="<?php echo esc_attr($timetable_events_settings["slug"]);?>" autocomplete="off" />
				</td>
			</tr>
			<tr valign="top">
				<th>
					<label for="timetable_events_settings_label_singular"><?php esc_html_e("Event label singular: ", "timetable"); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="timetable_events_settings_label_singular" id="timetable_events_settings_label_singular" value="<?php echo esc_attr($timetable_events_settings["label_singular"]);?>" autocomplete="off" />
				</td>
			</tr>
			<tr valign="top">
				<th>
					<label for="timetable_events_settings_label_plural"><?php esc_html_e("Event label plural: ", "timetable"); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="timetable_events_settings_label_plural" id="timetable_events_settings_label_plural" value="<?php echo esc_attr($timetable_events_settings["label_plural"]);?>" autocomplete="off" />

				</td>
			</tr>
			<tr valign="top" class="no-border">
				<td colspan="3">
					<input type="submit" class="button button-primary" name="timetable_events_settings_save" id="timetable_events_settings_save" value="<?php esc_attr_e('Save', 'timetable'); ?>" />
					<span class="spinner" style="float: none; margin: 0 10px;"></span>
				</td>
			</tr>
			<tr valign="top" class="tt_hide no-border">
				<td colspan="3">
					<div id="event_slug_info"></div>
				</td>
			</tr>
		</table>
	</div>
</form>