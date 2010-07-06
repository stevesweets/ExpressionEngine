/*jslint browser: true, devel: true, onevar: true, undef: true, nomen: true, eqeqeq: true, plusplus: true, bitwise: true, regexp: true, strict: true, newcap: true, immed: true */

/*global $, jQuery, EE */

/*!
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2010, EllisLab, Inc.
 * @license		http://expressionengine.com/docs/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */
"use strict";

$(document).ready(function() {

	var ajaxContentButtons = {},
		dialog_div = $('<div id=\"ajaxContent\" />'),
		msgBoxOpen, msgContainer, save_state, setup_hidden;
	
	ajaxContentButtons[EE.lang.close] = function() { $(this).dialog("close") };
	
	dialog_div.dialog({
		autoOpen: false,
		resizable: false,
		modal: true,
		position: "center",
		minHeight: "0px", // fix display bug, where the height of the dialog is too big
		buttons: ajaxContentButtons
	});
	
	if (EE.importantMessage) {
		msgBoxOpen = EE.importantMessage.state;
		msgContainer = $("#ee_important_message");
			
		save_state = function() {
			msgBoxOpen = ! msgBoxOpen;
			document.cookie="exp_home_msg_state="+(msgBoxOpen ? "open" : "closed");
		};
	
		setup_hidden = function() {
			$.ee_notice.show_info(function() {
				$.ee_notice.hide_info();
				msgContainer.removeClass("closed").show();
				save_state();
			});
		};
	
		msgContainer.find(".msg_open_close").click(function() {
			msgContainer.hide();
			setup_hidden();
			save_state();
		});
	
		if ( ! msgBoxOpen) {
			setup_hidden();
		}		
	}

	$("a.submenu").click(function() {
		if ($(this).data("working")) {
			return false;
		}
		else {
			$(this).data("working", true);
		}
		
		var url = $(this).attr("href"),
			that = $(this).parent(),
			submenu = that.find("ul"),
			dialog_title;

		if ($(this).hasClass("accordion")) {
			
			if (submenu.length > 0) {
				if ( ! that.hasClass("open")) {
					that.siblings(".open").toggleClass("open").children("ul").slideUp("fast");
				}

				submenu.slideToggle("fast");
				that.toggleClass("open");
			}
			
			$(this).data("working", false);
		}
		else {
			$(this).data("working", false);
			dialog_title = $(this).html();

			$("#ajaxContent").load(url+" .pageContents", function() {
				$("#ajaxContent").dialog("option", "title", dialog_title);
				$("#ajaxContent").dialog("open");
			});
		}

		return false;
	});
});
