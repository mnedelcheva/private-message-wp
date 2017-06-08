
	// Adds show/hide messages function to widget using local storage
	if (localStorage.getItem("widget_state") == "true") {
		document.getElementById(widget_messages).style.display = "block";
	}
	else {
		document.getElementById(widget_messages).style.display = "none";
	}

	function showHide(){
		if (document.getElementById(widget_messages).style.display = "none") {
			document.getElementById(widget_messages).style.display = "block";
			localStorage.setItem("widget_state", true);
		}
		else {
			document.getElementById(widget_messages).style.display = "none";
			localStorage.setItem("widget_state", false);
		}
	}